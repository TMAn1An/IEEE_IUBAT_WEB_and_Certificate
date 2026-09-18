<?php

namespace App\Services\Certificates\Verification;

/** One dynamic field's label + value, already confirmed public-safe to display. */
final readonly class PublicField
{
    public function __construct(
        public string $label,
        public string $value,
    ) {}
}
