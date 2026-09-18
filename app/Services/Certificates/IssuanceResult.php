<?php

namespace App\Services\Certificates;

use App\Models\Certificate;

final readonly class IssuanceResult
{
    /** @param  list<string>  $overflowWarnings */
    public function __construct(
        public Certificate $certificate,
        public array $overflowWarnings,
    ) {}
}
