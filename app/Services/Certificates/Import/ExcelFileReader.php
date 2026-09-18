<?php

namespace App\Services\Certificates\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetReaderException;
use RuntimeException;

/**
 * Reads the first worksheet of an uploaded .xlsx file into plain arrays.
 * Package choice: `phpoffice/phpspreadsheet` directly (not the
 * `maatwebsite/excel` Laravel wrapper) — this project only needs a
 * synchronous read of a few hundred rows plus a header row, not
 * maatwebsite's queued-export/import abstractions. See
 * docs/ARCHITECTURE.md §6 Dependencies.
 */
class ExcelFileReader
{
    /**
     * @return array{headers: list<string>, rows: list<list<string|null>>}
     */
    public function read(string $absolutePath): array
    {
        try {
            $spreadsheet = IOFactory::load($absolutePath);
        } catch (SpreadsheetReaderException $e) {
            throw new RuntimeException('Could not read this file as an Excel spreadsheet: '.$e->getMessage(), previous: $e);
        }

        $grid = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if ($grid === []) {
            throw new RuntimeException('The spreadsheet is empty.');
        }

        $headers = array_map(fn ($h) => trim((string) $h), array_shift($grid));

        // Drop fully-blank rows (common at the end of historical exports).
        $rows = array_values(array_filter($grid, function (array $row) {
            return collect($row)->contains(fn ($cell) => trim((string) $cell) !== '');
        }));

        return ['headers' => $headers, 'rows' => $rows];
    }
}
