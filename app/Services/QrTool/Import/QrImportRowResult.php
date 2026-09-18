<?php

namespace App\Services\QrTool\Import;

use Illuminate\Support\Carbon;

final readonly class QrImportRowResult
{
    /**
     * @param  int  $rowNumber  Real Excel row number (header is row 1).
     * @param  list<string>  $errors
     * @param  array<string, string>  $fieldValues  key => value, category fields only.
     * @param  string|null  $codeword  Preserved from the file when mapped+valid+unique; null means "generate a new one."
     * @param  string|null  $eventName  Per-row override of the category's own event_name, or null to use the category default.
     * @param  Carbon|null  $createdAt  Preserved original date, or null to use "now" at import time.
     */
    public function __construct(
        public int $rowNumber,
        public array $errors,
        public ?string $recipientName,
        public array $fieldValues,
        public ?string $codeword,
        public ?string $eventName,
        public ?Carbon $createdAt,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
