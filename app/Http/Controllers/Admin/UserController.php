<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        return view('admin.users.index', [
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', [
            'roles' => UserRole::cases(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $data['is_active'] ?? true;

        User::create($data);

        return redirect()->route('admin.users.index')->with('status', 'Admin user created.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', [
            'targetUser' => $user,
            'roles' => UserRole::cases(),
            'isLastActiveSuperAdmin' => $this->isLastActiveSuperAdmin($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if ($this->isLastActiveSuperAdmin($user) && $data['role'] !== UserRole::SuperAdmin->value) {
            return back()
                ->withInput()
                ->withErrors(['role' => 'This is the last active Super Admin — assign another Super Admin first.']);
        }

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('status', 'Admin user updated.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('toggleActive', $user);

        if ($user->is_active && $this->isLastActiveSuperAdmin($user)) {
            return back()->withErrors(['email' => 'You cannot deactivate the last active Super Admin.']);
        }

        if ($user->is($this->currentUser()) && $user->is_active) {
            return back()->withErrors(['email' => 'You cannot deactivate your own account.']);
        }

        $user->update(['is_active' => ! $user->is_active]);

        $status = $user->is_active ? 'activated' : 'deactivated';

        return back()->with('status', "Admin user {$status}.");
    }

    private function isLastActiveSuperAdmin(User $user): bool
    {
        if ($user->role !== UserRole::SuperAdmin || ! $user->is_active) {
            return false;
        }

        return User::where('role', UserRole::SuperAdmin)
            ->where('is_active', true)
            ->count() <= 1;
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
