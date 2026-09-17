<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;

/**
 * Local-development-only admin accounts. Refuses to run outside
 * local/testing so it can never accidentally seed a known password into a
 * real environment — the first production Super Admin is created with
 * `php artisan app:make-admin` instead (see docs/DEPLOYMENT_CPANEL.md).
 *
 * Credentials come from .env (SEED_ADMIN_EMAIL / SEED_ADMIN_PASSWORD /
 * SEED_MANAGER_EMAIL / SEED_MANAGER_PASSWORD) when set, falling back to
 * clearly-fake local defaults otherwise. Never commit real values for these
 * to .env — only .env.example's placeholders are version-controlled.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! App::environment(['local', 'testing'])) {
            $this->command?->warn('AdminUserSeeder skipped — only runs in local/testing environments.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'admin@ieee-iubat.test')],
            [
                'name' => 'Dev Super Admin',
                'password' => Hash::make(env('SEED_ADMIN_PASSWORD', 'dev-only-password')),
                'role' => UserRole::SuperAdmin,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => env('SEED_MANAGER_EMAIL', 'manager@ieee-iubat.test')],
            [
                'name' => 'Dev Certificate Manager',
                'password' => Hash::make(env('SEED_MANAGER_PASSWORD', 'dev-only-password')),
                'role' => UserRole::CertificateManager,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info('Seeded dev admin accounts (local/testing only — see AdminUserSeeder docblock).');
    }
}
