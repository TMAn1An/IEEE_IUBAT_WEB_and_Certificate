<?php

namespace App\Enums;

/**
 * Deliberately minimal compared to CertificateStatus -- no Reissued (the
 * simple tool has no reissue concept), no GenerationFailed (there's no PDF
 * generation step that could fail). Revoked exists for the same reason as
 * the advanced system's: the column/case should exist so verification can
 * correctly hide a revoked simple record even though no admin-facing revoke
 * action is built yet.
 */
enum QrCertificateStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Revoked => 'Revoked',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'badge--active',
            self::Revoked => 'badge--inactive',
        };
    }
}
