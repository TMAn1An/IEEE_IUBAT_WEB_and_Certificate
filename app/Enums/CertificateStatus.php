<?php

namespace App\Enums;

/**
 * See docs/CERTIFICATE_SYSTEM.md §Revocation / §Reissue. Revoke/reissue
 * behavior isn't built yet (Phase 8); this enum just gives the
 * `certificates.status` column a real, documented type.
 */
enum CertificateStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
    case Reissued = 'reissued';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Revoked => 'Revoked',
            self::Reissued => 'Reissued',
        };
    }
}
