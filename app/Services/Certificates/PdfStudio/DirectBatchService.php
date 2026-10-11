<?php

namespace App\Services\Certificates\PdfStudio;

use App\Enums\CertificateBatchReservationStatus;
use App\Enums\CertificateBatchStatus;
use App\Models\CertificateBatch;
use App\Models\CertificateBatchReservation;
use App\Models\CertificateTemplate;
use App\Models\QrCertificate;
use App\Models\User;
use App\Services\Certificates\CertificateNumberService;
use App\Services\Certificates\Import\ExcelFileReader;
use App\Services\Certificates\VerificationCodewordService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The direct-integration replacement for the manual-handoff
 * PdfEditorBridgeReservationService (see docs/PDF_STUDIO_INTEGRATION.md).
 * Validates participant data against the TEMPLATE'S SAVED EDITOR SCHEMA
 * (CertificateTemplate::$editor_schema, derived from the PDF Studio project
 * — not the old template_fields designer table, per the brief: one field
 * list, the editor's own), previews it, and on confirm mints the same
 * certificate_number/codeword pair the advanced system always has —
 * nothing new is invented here.
 */
class DirectBatchService
{
    public function __construct(
        private readonly ExcelFileReader $excelReader,
        private readonly CertificateNumberService $numberService,
        private readonly VerificationCodewordService $codewordService,
    ) {}

    /**
     * @param  array<string, UploadedFile>  $photosByOriginalName
     * @return array{token: string, columns: list<string>, rows: list<array<string,mixed>>, errors: list<string>, total_rows: int}
     */
    public function prepare(CertificateTemplate $template, UploadedFile $excel, array $photosByOriginalName): array
    {
        $schema = $template->editor_schema;
        if ($schema === null) {
            throw new RuntimeException('Save a PDF Studio project for this template before uploading participant data.');
        }

        $sheet = $this->excelReader->read($excel->getRealPath());
        $headers = $sheet['headers'];
        $rows = $sheet['rows'];

        $maxRows = (int) config('pdf-studio.max_batch_rows');
        if (count($rows) > $maxRows) {
            throw new RuntimeException('This spreadsheet has '.count($rows)." rows; the limit per batch is {$maxRows}.");
        }

        $duplicateHeaders = array_diff_assoc($headers, array_unique($headers));
        if ($duplicateHeaders !== []) {
            throw new RuntimeException('Duplicate column header(s): '.implode(', ', array_unique($duplicateHeaders)));
        }

        $expectedColumns = array_column($schema['fields'], 'column');
        $unknown = array_diff($headers, $expectedColumns);
        if ($unknown !== []) {
            throw new RuntimeException('Unknown column(s) not defined on this template: '.implode(', ', $unknown));
        }

        $missingRequired = [];
        foreach ($schema['fields'] as $field) {
            if ($field['required'] && ! in_array($field['column'], $headers, true)) {
                $missingRequired[] = $field['column'];
            }
        }
        if ($missingRequired !== []) {
            throw new RuntimeException('Missing required column(s): '.implode(', ', $missingRequired));
        }

        $errors = [];
        $previewRows = [];
        foreach ($rows as $i => $row) {
            $rowNumber = $i + 2;
            $values = array_combine($headers, array_pad($row, count($headers), null));

            $rowErrors = [];
            foreach ($schema['fields'] as $field) {
                $raw = $values[$field['column']] ?? null;
                // Preserve leading zeros / exact text: cast to string, never
                // float/int, so "0012" never becomes "12".
                $values[$field['column']] = $raw === null ? null : (string) $raw;

                if ($field['required'] && trim((string) ($values[$field['column']] ?? '')) === '') {
                    $rowErrors[] = "{$field['label']} is required";
                }

                if ($field['type'] === 'image' && trim((string) ($values[$field['column']] ?? '')) !== ''
                    && ! isset($photosByOriginalName[$values[$field['column']]])) {
                    $rowErrors[] = "Photo \"{$values[$field['column']]}\" was not found among the uploaded images";
                }
            }

            if ($rowErrors !== []) {
                $errors[] = "Row {$rowNumber}: ".implode('; ', $rowErrors);
            }

            $previewRows[] = ['row' => $rowNumber, 'values' => $values, 'errors' => $rowErrors];
        }

        $token = (string) Str::uuid();
        $dir = "certificate-batches/prepared/{$token}";
        Storage::disk('local')->putFileAs($dir, $excel, 'participants.xlsx');
        foreach ($photosByOriginalName as $originalName => $file) {
            Storage::disk('local')->putFileAs("{$dir}/photos", $file, $originalName);
        }
        Storage::disk('local')->put("{$dir}/meta.json", json_encode([
            'template_id' => $template->id,
            'editor_schema_version' => $template->editor_schema_version,
        ]));

        return [
            'token' => $token,
            'columns' => $headers,
            'rows' => array_map(fn ($r) => ['row' => $r['row'], 'values' => $r['values'], 'errors' => $r['errors']], array_slice($previewRows, 0, 50)),
            'errors' => $errors,
            'total_rows' => count($rows),
        ];
    }

    /**
     * Idempotent: a repeat confirm with the same idempotency_key returns the
     * already-created batch instead of reserving a second one.
     */
    public function confirm(CertificateTemplate $template, string $token, string $idempotencyKey, User $admin): CertificateBatch
    {
        $existing = CertificateBatch::where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $dir = "certificate-batches/prepared/{$token}";
        $excelPath = Storage::disk('local')->path("{$dir}/participants.xlsx");
        if (! Storage::disk('local')->exists("{$dir}/participants.xlsx")) {
            throw new RuntimeException('This preview has expired. Upload the spreadsheet again.');
        }

        $meta = json_decode(Storage::disk('local')->get("{$dir}/meta.json"), true);
        if ((int) $meta['template_id'] !== $template->id) {
            throw new RuntimeException('This preview belongs to a different template.');
        }

        $schema = $template->editor_schema;
        $sheet = $this->excelReader->read($excelPath);
        $headers = $sheet['headers'];
        $rows = $sheet['rows'];

        // editor_project_path is pinned onto the batch, not just its schema
        // version: TemplateProjectService::save() keeps every prior bundle
        // file around precisely so this path stays valid even after the
        // template is later re-saved. See
        // docs/PDF_STUDIO_INTEGRATION.md's "Batch template immutability".
        try {
            $batch = DB::transaction(fn () => CertificateBatch::create([
                'certificate_template_id' => $template->id,
                'source' => 'pdf_studio',
                'name' => ($schema['project_name'] ?? $template->name).' — '.now()->toDateTimeString(),
                'status' => CertificateBatchStatus::Processing,
                'total_rows' => count($rows),
                'created_by' => $admin->id,
                'idempotency_key' => $idempotencyKey,
                'editor_schema_version' => $template->editor_schema_version,
                'editor_project_path' => $template->editor_project_path,
            ]));
        } catch (UniqueConstraintViolationException) {
            // Lost a race against a concurrent confirm() using the same
            // idempotency key: the other request's row now exists under the
            // unique constraint on certificate_batches.idempotency_key — the
            // DB constraint is the actual guarantee here, this catch only
            // makes the loser return the winner's batch instead of a 500.
            return CertificateBatch::where('idempotency_key', $idempotencyKey)->firstOrFail();
        }

        $photosDir = "{$dir}/photos";
        $recipientColumn = null;
        foreach ($schema['fields'] as $f) {
            if ($f['id'] === $schema['recipient_field_id']) {
                $recipientColumn = $f['column'];
            }
        }

        $successCount = 0;
        $failCount = 0;
        foreach ($rows as $i => $row) {
            $values = array_combine($headers, array_pad($row, count($headers), null));
            $values = array_map(fn ($v) => $v === null ? null : (string) $v, $values);

            try {
                DB::transaction(function () use ($batch, $i, $values, $schema, $recipientColumn, $photosDir, &$successCount) {
                    $certificateNumber = $this->numberService->next();
                    $codeword = $this->uniqueCodeword();

                    $data = [];
                    $photoStoredPath = null;
                    foreach ($schema['fields'] as $f) {
                        $value = $values[$f['column']] ?? null;
                        $data[$f['id']] = $value;
                        if ($f['type'] === 'image' && $value) {
                            $src = "{$photosDir}/{$value}";
                            if (Storage::disk('local')->exists($src)) {
                                $photoStoredPath = "certificate-batches/{$batch->id}/photos/{$codeword}-".basename((string) $value);
                                Storage::disk('local')->copy($src, $photoStoredPath);
                            }
                        }
                    }

                    $batch->reservations()->create([
                        'row_index' => $i,
                        'certificate_number' => $certificateNumber,
                        'codeword' => $codeword,
                        'recipient_name' => $recipientColumn ? ($values[$recipientColumn] ?? null) : null,
                        'data' => $data,
                        'qr_filename' => $codeword.'.png',
                        'status' => CertificateBatchReservationStatus::Reserved,
                        'photo_path' => $photoStoredPath,
                    ]);

                    $successCount++;
                });
            } catch (\Throwable) {
                $failCount++;
            }
        }

        $batch->update([
            'successful_rows' => $successCount,
            'failed_rows' => $failCount,
            'status' => match (true) {
                $successCount === 0 => CertificateBatchStatus::Failed,
                $failCount === 0 => CertificateBatchStatus::Completed,
                default => CertificateBatchStatus::Partial,
            },
        ]);

        Storage::disk('local')->deleteDirectory($dir);

        return $batch->fresh();
    }

    private function uniqueCodeword(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $codeword = $this->codewordService->generate();
            // Uniqueness must hold across every record/reservation type that
            // can affect public verification — not just this table — since
            // CertificateVerificationService resolves codewords across both
            // the advanced and simple-QR-tool sources.
            $taken = $this->codewordService->isUnique($codeword) === false
                || CertificateBatchReservation::where('codeword', $codeword)->exists()
                || QrCertificate::where('codeword', $codeword)->exists();

            if (! $taken) {
                return $codeword;
            }
        }

        throw new RuntimeException('Could not generate a unique verification codeword after several attempts.');
    }
}
