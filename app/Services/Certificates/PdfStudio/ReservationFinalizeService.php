<?php

namespace App\Services\Certificates\PdfStudio;

use App\Enums\CertificateBatchReservationStatus;
use App\Enums\CertificateBatchStatus;
use App\Enums\CertificateStatus;
use App\Models\Certificate;
use App\Models\CertificateBatchReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Finalizes one reservation at a time, identified ONLY by its server-issued
 * reservation id — never a filename (see docs/PDF_STUDIO_INTEGRATION.md).
 * Idempotent: re-finalizing an already-finalized reservation with the SAME
 * bytes (by hash) returns the existing result; with DIFFERENT bytes it's a
 * 409 conflict, never a silent overwrite — certificates are never
 * destructively overwritten (CLAUDE.md).
 */
class ReservationFinalizeService
{
    private const MAX_PDF_BYTES = 15 * 1024 * 1024;

    /**
     * @return array{status: 'finalized'|'already_finalized'|'conflict', certificate_id: ?int, message?: string}
     */
    public function finalize(CertificateBatchReservation $reservation, string $pdfBytes, User $admin): array
    {
        if (substr($pdfBytes, 0, 5) !== '%PDF-') {
            throw new RuntimeException('The uploaded file does not look like a valid PDF.');
        }
        if (strlen($pdfBytes) > self::MAX_PDF_BYTES) {
            throw new RuntimeException('The PDF exceeds the 15MB per-certificate limit.');
        }

        return DB::transaction(function () use ($reservation, $pdfBytes, $admin) {
            $locked = CertificateBatchReservation::query()
                ->where('id', $reservation->id)
                ->lockForUpdate()
                ->first();

            if ($locked->status === CertificateBatchReservationStatus::Finalized) {
                $certificate = $locked->certificate;
                if ($certificate !== null && Storage::disk('local')->exists($certificate->pdf_path)) {
                    $existingHash = hash('sha256', Storage::disk('local')->get($certificate->pdf_path));
                    if ($existingHash === hash('sha256', $pdfBytes)) {
                        return ['status' => 'already_finalized', 'certificate_id' => $certificate->id];
                    }
                }

                return [
                    'status' => 'conflict',
                    'certificate_id' => $certificate?->id,
                    'message' => 'This row is already finalized with a different PDF. Certificates are never overwritten — contact a Super Admin to revoke and reissue if this certificate is genuinely wrong.',
                ];
            }

            if ($locked->status === CertificateBatchReservationStatus::Failed) {
                $locked->update(['status' => CertificateBatchReservationStatus::Reserved, 'error_message' => null]);
            }

            $pdfPath = sprintf('certificates/%d/%s.pdf', now()->year, (string) Str::uuid());
            Storage::disk('local')->put($pdfPath, $pdfBytes);

            try {
                $certificate = Certificate::create([
                    'certificate_template_id' => $locked->batch->certificate_template_id,
                    'certificate_batch_id' => $locked->certificate_batch_id,
                    'certificate_number' => $locked->certificate_number,
                    'codeword' => $locked->codeword,
                    'recipient_name' => $locked->recipient_name,
                    'data' => $locked->data,
                    'status' => CertificateStatus::Active,
                    'issued_at' => now(),
                    'pdf_path' => $pdfPath,
                    'created_by' => $admin->id,
                ]);
            } catch (\Throwable $e) {
                Storage::disk('local')->delete($pdfPath);
                $locked->update(['status' => CertificateBatchReservationStatus::Failed, 'error_message' => $e->getMessage()]);
                $this->refreshBatchCounts($locked);

                throw $e;
            }

            $locked->update([
                'status' => CertificateBatchReservationStatus::Finalized,
                'certificate_id' => $certificate->id,
                'error_message' => null,
            ]);

            $this->refreshBatchCounts($locked);

            return ['status' => 'finalized', 'certificate_id' => $certificate->id];
        });
    }

    private function refreshBatchCounts(CertificateBatchReservation $reservation): void
    {
        $batch = $reservation->batch()->lockForUpdate()->first();
        $finalized = $batch->reservations()->where('status', CertificateBatchReservationStatus::Finalized)->count();
        $stillOpen = $batch->reservations()->whereIn('status', [
            CertificateBatchReservationStatus::Reserved,
        ])->count();

        $batch->update([
            'successful_rows' => $finalized,
            'failed_rows' => $batch->total_rows - $finalized - $stillOpen,
            'status' => match (true) {
                $stillOpen === 0 && $finalized === $batch->total_rows => CertificateBatchStatus::Completed,
                $finalized === 0 && $stillOpen === 0 => CertificateBatchStatus::Failed,
                default => CertificateBatchStatus::Partial,
            },
        ]);
    }
}
