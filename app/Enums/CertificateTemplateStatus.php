<?php

namespace App\Enums;

/**
 * Only `active` templates can be used for certificate generation (enforced
 * when that generation logic is built in a later phase — this enum is
 * database/model readiness only for now, per the Phase 2 scope).
 */
enum CertificateTemplateStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Archived => 'Archived',
        };
    }
}
