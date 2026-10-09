<?php

namespace App\Services\Forms;

use RuntimeException;

/** The builder tried to save on top of a version someone else already replaced. */
final class FormVersionConflictException extends RuntimeException
{
    public function __construct(public readonly int $currentVersion)
    {
        parent::__construct('This form was changed somewhere else since you opened it.');
    }
}
