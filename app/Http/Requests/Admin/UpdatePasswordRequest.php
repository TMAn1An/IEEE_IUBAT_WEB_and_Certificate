<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Self-service "change my own password" — there is no {user} route
 * parameter anywhere in this request (see routes/admin.php), so there is no
 * account to act on other than $this->user(), the authenticated admin. That
 * is what makes "a user can only change their own password" true by
 * construction, not just by convention — see
 * Admin\ProfileController::updatePassword().
 *
 * current_password is only checked for presence here. Its actual
 * verification (Hash::check) happens in the controller, not as a validation
 * rule, so a wrong current password can participate in the same rate-limit
 * ordering as Admin\AuthController::login() (check throttle, then verify,
 * then hit()/clear()) — a failed validation rule aborts before any
 * controller code runs, which would make that ordering impossible.
 */
class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
