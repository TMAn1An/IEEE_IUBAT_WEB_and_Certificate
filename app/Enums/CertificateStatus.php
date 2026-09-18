<?php

namespace App\Enums;

/**
 * See docs/CERTIFICATE_SYSTEM.md §Revocation / §Reissue. Revoke/reissue
 * behavior isn't built yet (Phase 8); this enum just gives the
 * `certificates.status` column a real, documented type.
 *
 * `GenerationFailed` exists for schema-readiness only as of Phase 5:
 * CertificateIssuanceService's synchronous flow never actually persists a
 * row in this state (PDF bytes are rendered fully in memory before any
 * database write happens, so a rendering failure never reaches the
 * database at all — see docs/CERTIFICATE_SYSTEM.md §Failure handling). It's
 * here so a future async/queued generation path can use it without another
 * migration.
 */
enum CertificateStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
    case Reissued = 'reissued';
    case GenerationFailed = 'generation_failed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Revoked => 'Revoked',
            self::Reissued => 'Reissued',
            self::GenerationFailed => 'Generation failed',
        };
    }

    /** CSS class for the admin UI's status badge — see public/css/admin.css. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'badge--active',
            self::Revoked => 'badge--inactive',
            self::Reissued => 'badge--archived',
            self::GenerationFailed => 'badge--inactive',
        };
    }
}
