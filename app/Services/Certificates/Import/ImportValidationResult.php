<?php

namespace App\Services\Certificates\Import;

final readonly class ImportValidationResult
{
    /**
     * @param  list<string>  $mappingErrors  Non-empty means validation stopped before looking at any row — e.g. a required field wasn't mapped at all.
     * @param  list<ImportRowResult>  $rows  All rows in original file order, valid and invalid mixed — callers filter with isValid().
     */
    public function __construct(
        public array $mappingErrors,
        public array $rows,
    ) {}

    public function hasMappingErrors(): bool
    {
        return $this->mappingErrors !== [];
    }

    /** @return list<ImportRowResult> */
    public function validRows(): array
    {
        return array_values(array_filter($this->rows, fn (ImportRowResult $r) => $r->isValid()));
    }

    /** @return list<ImportRowResult> */
    public function invalidRows(): array
    {
        return array_values(array_filter($this->rows, fn (ImportRowResult $r) => ! $r->isValid()));
    }
}
