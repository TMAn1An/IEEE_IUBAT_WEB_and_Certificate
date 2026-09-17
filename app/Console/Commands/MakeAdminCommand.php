<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * How the first real Super Admin gets created — interactively, on the
 * target server, so no password ever passes through source control, a
 * seeder, or a committed file. See docs/DEPLOYMENT_CPANEL.md.
 *
 * Usage: php artisan app:make-admin
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'app:make-admin
        {--name= : Full name}
        {--email= : Login email}
        {--role=super_admin : super_admin or certificate_manager}';

    protected $description = 'Create an admin user (interactive password prompt; intended for the first production Super Admin)';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $roleValue = $this->option('role');
        $password = $this->secret('Password');
        $passwordConfirmation = $this->secret('Confirm password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'role' => $roleValue,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'role' => UserRole::from($roleValue),
            'password' => Hash::make($password),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->info("Admin user created: {$user->email} ({$user->role->label()})");

        return self::SUCCESS;
    }
}
