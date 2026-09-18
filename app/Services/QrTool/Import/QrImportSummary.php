<?php

namespace App\Services\QrTool\Import;

final readonly class QrImportSummary
{
    public function __construct(
        public int $imported,
        public int $skipped,
        public int $codewordsPreserved,
        public int $newCodewordsGenerated,
        /** @var list<string> */
        public array $failedRowMessages,
    ) {}
}
