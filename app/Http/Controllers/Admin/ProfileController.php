<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Self-service "My Profile": view your own account details, change your own
 * password. Every action here acts on $request->user() only — there is no
 * {user} route parameter anywhere in routes/admin.php for this controller,
 * so there is no way to reach or modify another account through it at all
 * (see docs/SECURITY.md). Super Admin's ability to manage *other* users'
 * accounts/roles/active status stays entirely in Admin\UserController,
 * untouched by this controller.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $throttleKey = 'password-update:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'current_password' => "Too many attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $validated = $request->validated();

        // Laravel's own password-hash verification primitive — the same one
        // Auth::attempt() and the `current_password` validation rule use
        // internally — called here directly (rather than as a validation
        // rule) so a wrong current password hits the rate limiter before
        // failing; see UpdatePasswordRequest's docblock.
        if (! Hash::check($validated['current_password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'current_password' => 'Your current password is incorrect.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Explicit single-column write, never a mass $user->update($validated)
        // or $user->fill($validated) — $validated only ever contains
        // current_password/password/password_confirmation anyway (see
        // UpdatePasswordRequest::rules()), but writing exactly the one
        // column keeps "this endpoint can only ever change a password"
        // true by construction, not just by what happens to be absent from
        // today's request shape. role/is_active/email are never touched
        // here, regardless of what a caller sends.
        $user->forceFill(['password' => Hash::make($validated['password'])])->save();

        // Rotate the current session — same session-fixation defense
        // Admin\AuthController::login() uses after a successful login.
        $request->session()->regenerate();

        // Then invalidate every *other* session this user has open. The
        // `database` session driver (config/session.php) stores one row per
        // session, keyed by id, with a user_id column (see the sessions
        // table in database/migrations/0001_01_01_000000_create_users_table.php)
        // — deleting every row for this user except the one just
        // regenerated logs every other device/browser out on its next
        // request, without needing the AuthenticateSession middleware
        // (and its extra per-request password-hash comparison) added to
        // every admin route. A no-op under any other session driver, since
        // nothing is ever written to that table to begin with.
        if (config('session.driver') === 'database') {
            DB::table('sessions')
                ->where('user_id', $user->getKey())
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        return redirect()
            ->route('admin.profile.edit')
            ->with('status', 'Password changed. You have been kept signed in here; any other signed-in sessions have been logged out.');
    }
}
