<?php

namespace App\Enums;

/** Whether a form can be filled in right now, and if not, why. See App\Services\Forms\FormAvailability. */
enum FormAvailabilityState: string
{
    case Open = 'open';
    /** Draft/deactivated/archived, or private and the visitor isn't signed in: the page 404s. */
    case Unavailable = 'unavailable';
    case NotOpenYet = 'not_open_yet';
    case Closed = 'closed';
    case LimitReached = 'limit_reached';
    case AlreadySubmitted = 'already_submitted';

    public function message(): string
    {
        return match ($this) {
            self::Open => '',
            self::Unavailable => 'This form is not available.',
            self::NotOpenYet => 'This form is not open for responses yet.',
            self::Closed => 'This form is closed and no longer accepts responses.',
            self::LimitReached => 'This form has reached its response limit.',
            self::AlreadySubmitted => 'You have already submitted this form.',
        };
    }
}
