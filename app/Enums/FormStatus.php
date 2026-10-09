<?php

namespace App\Enums;

/**
 * Lifecycle of a dynamic form. Only `Active` forms accept public
 * submissions (further gated by the form's own schedule/limit settings --
 * see App\Services\Forms\FormAvailability). `Archived` is the terminal
 * "retired" state used instead of deletion: read-only in the builder, but
 * its submissions stay viewable/exportable.
 */
enum FormStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Published',
            self::Inactive => 'Deactivated',
            self::Archived => 'Archived',
        };
    }

    public function isEditable(): bool
    {
        return $this !== self::Archived;
    }
}
