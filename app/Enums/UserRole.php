<?php

namespace App\Enums;

/**
 * The two admin roles for Version 1 — see docs/SECURITY.md and
 * docs/PROJECT_REQUIREMENTS.md for what each can do. There is no public
 * registration; every user row is created by a Super Admin or the
 * `app:make-admin` console command.
 */
enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case CertificateManager = 'certificate_manager';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::CertificateManager => 'Certificate Manager',
        };
    }
}
