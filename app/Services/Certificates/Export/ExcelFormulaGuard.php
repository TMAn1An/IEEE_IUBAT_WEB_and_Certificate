<?php

namespace App\Services\Certificates\Export;

/**
 * Formula-injection guard for every Excel export in the project (advanced
 * and simple-QR-tool alike) — required by CLAUDE.md's non-negotiable
 * security rules, matching the pattern already used in the archived Flask
 * tool's `safe_excel_text()`. A string value that starts with `=`, `+`,
 * `-`, or `@` is prefixed with a leading apostrophe so a cell is never
 * auto-interpreted as a formula by PhpSpreadsheet's type inference (or by
 * Excel/Sheets on open).
 */
final class ExcelFormulaGuard
{
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@'];

    public static function sanitize(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], self::DANGEROUS_PREFIXES, true) ? "'".$value : $value;
    }

    /** @param  list<mixed>  $row
     * @return list<mixed> */
    public static function sanitizeRow(array $row): array
    {
        return array_map(self::sanitize(...), $row);
    }
}
