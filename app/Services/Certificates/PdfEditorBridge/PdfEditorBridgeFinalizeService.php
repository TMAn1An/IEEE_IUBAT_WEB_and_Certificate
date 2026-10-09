<?php

namespace App\Services\Certificates\PdfEditorBridge;

use App\Enums\CertificateBatchReservationStatus;
use App\Enums\CertificateBatchStatus;
use App\Enums\CertificateStatus;
use App\Models\Certificate;
use App\Models\CertificateBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The "finalize" half of the PDF Editor Bridge — see
 * docs/CERTIFICATE_SYSTEM.md §PDF Editor Bridge.
 *
 * Takes the ZIP (or single PDF) the admin exported from the separate,
 * unmodified PDF Template Studio editor, matches each file to a `reserved`
 * CertificateBatchReservation by filename (minus extension) == codeword —
 * which is exactly what that editor's existing, unmodified "custom filename
 * pattern" bulk-export feature already supports by mapping a spreadsheet
 * column into the filename — and only THEN creates the real `certificates`
 * row, preserving CertificateIssuanceService's "no certificates row without
 * a matching stored PDF" invariant for this path too.
 *
 * One file's failure (corrupt PDF, no matching reservation) never aborts
 * the rest of the upload — each match is its own DB transaction.
 */
class PdfEditorBridgeFinalizeService
{
    private const MAX_PDF_BYTES = 15 * 1024 * 1024;

    private const MAX_ENTRIES = 1000;

    /**
     * @return array{finalized: int, failed: int, errors: list<string>}
     */
    public function finalize(CertificateBatch $batch, string $uploadedAbsolutePath, string $originalFilename, User $admin): array
    {
        $isPdf = str_ends_with(strtolower($originalFilename), '.pdf');

        $pdfs = $isPdf
            ? [$originalFilename => file_get_contents($uploadedAbsolutePath)]
            : $this->extractZip($uploadedAbsolutePath);

        $errors = [];
        $finalized = 0;

        foreach ($pdfs as $filename => $bytes) {
            if (! str_ends_with(strtolower($filename), '.pdf')) {
                continue;
            }

            $codeword = pathinfo($filename, PATHINFO_FILENAME);

            try {
                $this->finalizeOne($batch, $codeword, $bytes, $admin);
                $finalized++;
            } catch (\Throwable $e) {
                $errors[] = "{$filename}: ".$e->getMessage();
            }
        }

        $batch->refresh();
        $finalizedCount = $batch->reservations()->where('status', CertificateBatchReservationStatus::Finalized)->count();
        $stillReserved = $batch->reservations()->where('status', CertificateBatchReservationStatus::Reserved)->count();

        $batch->update([
            'successful_rows' => $finalizedCount,
            'failed_rows' => $batch->total_rows - $finalizedCount,
            'status' => match (true) {
                $stillReserved === 0 && $finalizedCount === $batch->total_rows => CertificateBatchStatus::Completed,
                $finalizedCount === 0 => CertificateBatchStatus::Failed,
                default => CertificateBatchStatus::Partial,
            },
        ]);

        return ['finalized' => $finalized, 'failed' => count($errors), 'errors' => $errors];
    }

    private function finalizeOne(CertificateBatch $batch, string $codeword, string $bytes, User $admin): void
    {
        if (substr($bytes, 0, 5) !== '%PDF-') {
            throw new RuntimeException('does not look like a valid PDF');
        }

        if (strlen($bytes) > self::MAX_PDF_BYTES) {
            throw new RuntimeException('exceeds the 15MB per-certificate limit');
        }

        DB::transaction(function () use ($batch, $codeword, $bytes, $admin) {
            $reservation = $batch->reservations()
                ->where('codeword', $codeword)
                ->lockForUpdate()
                ->first();

            if ($reservation === null) {
                throw new RuntimeException('no matching reservation for this codeword in this batch');
            }

            if ($reservation->status !== CertificateBatchReservationStatus::Reserved) {
                throw new RuntimeException('already finalized or failed — skipped');
            }

            $pdfPath = sprintf('certificates/%d/%s.pdf', now()->year, (string) Str::uuid());
            Storage::disk('local')->put($pdfPath, $bytes);

            try {
                $certificate = Certificate::create([
                    'certificate_template_id' => $batch->certificate_template_id,
                    'certificate_batch_id' => $batch->id,
                    'certificate_number' => $reservation->certificate_number,
                    'codeword' => $reservation->codeword,
                    'recipient_name' => $reservation->recipient_name,
                    'data' => $reservation->data,
                    'status' => CertificateStatus::Active,
                    'issued_at' => now(),
                    'pdf_path' => $pdfPath,
                    // No template_snapshot/layout_snapshot: this path never
                    // renders via CertificatePdfService, so there is no
                    // position/style layout to snapshot — the editor's own
                    // project file is the durable record of how this PDF
                    // was laid out. Documented limitation, see
                    // docs/CERTIFICATE_SYSTEM.md §PDF Editor Bridge.
                    'created_by' => $admin->id,
                ]);
            } catch (\Throwable $e) {
                Storage::disk('local')->delete($pdfPath);

                throw $e;
            }

            $reservation->update([
                'status' => CertificateBatchReservationStatus::Finalized,
                'certificate_id' => $certificate->id,
            ]);
        });
    }

    /** @return array<string, string> filename => bytes */
    private function extractZip(string $absolutePath): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException('Could not open the uploaded file as a ZIP archive.');
        }

        if ($zip->numFiles > self::MAX_ENTRIES) {
            $zip->close();
            throw new RuntimeException(sprintf('The ZIP has more than %d entries.', self::MAX_ENTRIES));
        }

        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || str_contains($name, '/') || str_contains($name, '..')) {
                continue; // flat ZIP only, no path traversal
            }

            $contents = $zip->getFromIndex($i);
            if ($contents !== false && strlen($contents) <= self::MAX_PDF_BYTES) {
                $files[$name] = $contents;
            }
        }

        $zip->close();

        return $files;
    }
}
