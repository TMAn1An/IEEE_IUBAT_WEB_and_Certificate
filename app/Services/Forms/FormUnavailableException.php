<?php

namespace App\Services\Forms;

use App\Enums\FormAvailabilityState;
use RuntimeException;

/** The form exists but can't take this submission (closed, limit reached, already submitted, ...). */
final class FormUnavailableException extends RuntimeException
{
    public function __construct(public readonly FormAvailabilityState $state)
    {
        parent::__construct($state->message());
    }
}
