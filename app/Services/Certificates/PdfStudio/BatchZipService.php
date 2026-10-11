<?php

namespace App\Services\Certificates\PdfStudio;

use App\Enums\CertificateBatchReservationStatus;
use App\Models\CertificateBatch;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Streams a batch's already-stored certificate PDFs as a ZIP — never
 * re-renders, never mints new records. Filenames are numbered by the row's
 * original spreadsheet order so extraction order matches the upload order
 * regardless of how a ZIP tool sorts entries.
 */
class BatchZipService
{
    /** @return array{path: string, incomplete: bool, missing: list<string>} */
    public function build(CertificateBatch $batch): array
    {
        $reservations = $batch->reservations()->with('certificate')->orderBy('row_index')->get();
        $total = $reservations->count();
        $digits = max(3, strlen((string) $total));

        $tmpPath = tempnam(sys_get_temp_dir(), 'batch-zip');
        $zip = new ZipArchive;
        if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the batch ZIP.');
        }

        $missing = [];
        foreach ($reservations as $position => $reservation) {
            $certificate = $reservation->certificate;
            if ($reservation->status !== CertificateBatchReservationStatus::Finalized || $certificate === null
                || ! Storage::disk('local')->exists($certificate->pdf_path)) {
                $missing[] = sprintf('Row %d (%s)', $reservation->row_index + 2, $reservation->recipient_name ?? $reservation->certificate_number);

                continue;
            }

            $number = str_pad((string) ($position + 1), $digits, '0', STR_PAD_LEFT);
            $safeName = preg_replace('/[^A-Za-z0-9 _.-]/', '_', $reservation->recipient_name ?? $reservation->certificate_number);
            $filename = "{$number}-{$safeName}-{$certificate->certificate_number}.pdf";

            $zip->addFile(Storage::disk('local')->path($certificate->pdf_path), $filename);
        }

        if ($missing !== []) {
            $zip->addFromString(
                '_INCOMPLETE_BATCH.txt',
                "This batch is not fully finalized. The following rows are missing from this ZIP:\n\n".implode("\n", $missing)."\n"
            );
        }

        $zip->close();

        return ['path' => $tmpPath, 'incomplete' => $missing !== [], 'missing' => $missing];
    }
}
