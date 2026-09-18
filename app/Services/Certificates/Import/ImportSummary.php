<?php

namespace App\Services\Certificates\Import;

final readonly class ImportSummary
{
    public function __construct(
        public int $imported,
        public int $skipped,
        public int $codewordsPreserved,
        public int $newCodewordsGenerated,
        public int $certificateNumbersPreserved,
        public int $newCertificateNumbersGenerated,
        /** @var list<string> */
        public array $failedRowMessages,
    ) {}
}
