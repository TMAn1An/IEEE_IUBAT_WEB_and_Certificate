<?php

namespace App\Services\Certificates\Import;

final readonly class ImportRowResult
{
    /**
     * @param  int  $rowNumber  The real Excel row number (header is row 1), for admin-facing error messages.
     * @param  list<string>  $errors
     * @param  array<string, string>  $fieldValues  field_key => value, assignable fields only.
     * @param  string|null  $codeword  Preserved from the file when mapped+valid+unique; null means "generate a new one."
     * @param  string|null  $certificateNumber  Preserved from the file when mapped+valid+unique; null means "generate a new one."
     */
    public function __construct(
        public int $rowNumber,
        public array $errors,
        public ?string $recipientName,
        public array $fieldValues,
        public ?string $codeword,
        public ?string $certificateNumber,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
