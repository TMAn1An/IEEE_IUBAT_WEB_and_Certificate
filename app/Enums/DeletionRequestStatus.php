<?php

namespace App\Enums;

enum DeletionRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Completed => 'Completed',
        };
    }

    /** CSS class for the admin UI's status badge — see public/css/admin.css. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'badge--archived',
            self::Approved => 'badge--active',
            self::Rejected => 'badge--inactive',
            self::Completed => 'badge--inactive',
        };
    }
}
