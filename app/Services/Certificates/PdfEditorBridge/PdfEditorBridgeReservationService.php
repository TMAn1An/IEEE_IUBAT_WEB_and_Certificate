<?php

namespace App\Services\Certificates\PdfEditorBridge;

use App\Enums\CertificateBatchReservationStatus;
use App\Enums\CertificateBatchStatus;
use App\Models\CertificateBatch;
use App\Models\CertificateBatchReservation;
use App\Models\CertificateTemplate;
use App\Models\User;
use App\Services\Certificates\CertificateNumberService;
use App\Services\Certificates\Export\ExcelFormulaGuard;
use App\Services\Certificates\Import\ExcelFileReader;
use App\Services\Certificates\QrCodeService;
use App\Services\Certificates\VerificationCodewordService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;

/**
 * The "reserve" half of the PDF Editor Bridge (see
 * docs/CERTIFICATE_SYSTEM.md §PDF Editor Bridge for the full design and why
 * this is the smallest safe way to connect the separate, client-side PDF
 * Template Studio editor to this app's advanced certificate system without
 * touching that editor's rendering engine at all).
 *
 * Given a recipients spreadsheet and an active CertificateTemplate (used
 * here only for its field SCHEMA — label/key/type/required — never for PDF
 * rendering, since Laravel never renders the PDF in this path), this mints
 * a real certificate_number + codeword per valid row (same services the
 * advanced single-certificate path uses), generates that row's QR PNG, and
 * hands back an augmented spreadsheet + a ZIP of QR images for the admin to
 * load into the (separately maintained) PDF editor — which already matches
 * image fields to files by spreadsheet-column filename with zero code
 * changes needed there.
 *
 * Deliberately does NOT insert into `certificates` here — see the
 * migration's docstring for why a reservation is a distinct, lesser
 * commitment than an issued certificate.
 */
class PdfEditorBridgeReservationService
{
    /** Hard cap so one request can't be asked to process an unbounded spreadsheet. */
    public const MAX_ROWS = 1000;

    private const CHUNK_SIZE = 100;

    public function __construct(
        private readonly ExcelFileReader $excelReader,
        private readonly CertificateNumberService $numberService,
        private readonly VerificationCodewordService $codewordService,
        private readonly QrCodeService $qrCodeService,
    ) {}

    /**
     * @return array{batch: CertificateBatch, errors: list<string>}
     */
    public function reserve(CertificateTemplate $template, string $excelAbsolutePath, User $admin): array
    {
        $sheet = $this->excelReader->read($excelAbsolutePath);
        $headers = $sheet['headers'];
        $rows = $sheet['rows'];

        if (count($rows) > self::MAX_ROWS) {
            throw new RuntimeException(sprintf(
                'This spreadsheet has %d rows; the PDF Editor Bridge accepts at most %d at a time.',
                count($rows),
                self::MAX_ROWS
            ));
        }

        $fields = $template->fields->filter(fn ($f) => $f->field_type->isAssignable());
        $fieldKeys = $fields->pluck('field_key')->all();
        $recipientField = $fields->first(fn ($f) => $f->is_recipient_name);

        $unknownColumns = array_diff($headers, $fieldKeys);
        if ($unknownColumns !== []) {
            throw new RuntimeException('Unknown column(s) not defined on this template: '.implode(', ', $unknownColumns));
        }

        $missingRequired = [];
        foreach ($fields->where('is_required', true) as $field) {
            if (! in_array($field->field_key, $headers, true)) {
                $missingRequired[] = $field->field_key;
            }
        }
        if ($missingRequired !== []) {
            throw new RuntimeException('Missing required column(s): '.implode(', ', $missingRequired));
        }

        $batch = DB::transaction(function () use ($template, $admin, $rows) {
            return CertificateBatch::create([
                'certificate_template_id' => $template->id,
                'source' => 'pdf_editor_bridge',
                'name' => 'PDF Editor Bridge — '.now()->toDateTimeString(),
                'status' => CertificateBatchStatus::Processing,
                'total_rows' => count($rows),
                'created_by' => $admin->id,
            ]);
        });

        $errors = [];
        $qrFiles = [];
        $exportRows = [];
        $successCount = 0;

        foreach (array_chunk($rows, self::CHUNK_SIZE, true) as $chunk) {
            foreach ($chunk as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2; // header is row 1, data starts at row 2
                $values = array_combine($headers, array_pad($row, count($headers), null));

                $rowErrors = [];
                foreach ($fields->where('is_required', true) as $field) {
                    if (trim((string) ($values[$field->field_key] ?? '')) === '') {
                        $rowErrors[] = "{$field->label} is required";
                    }
                }

                if ($rowErrors !== []) {
                    $errors[] = "Row {$rowNumber}: ".implode('; ', $rowErrors);

                    continue;
                }

                try {
                    DB::transaction(function () use (
                        $template,
                        $values,
                        $rowIndex,
                        $recipientField,
                        $batch,
                        &$qrFiles,
                        &$exportRows,
                        &$successCount
                    ) {
                        $certificateNumber = $this->numberService->next();
                        $codeword = $this->uniqueCodeword();
                        $qrFilename = $codeword.'.png';

                        $data = [];
                        foreach ($template->fields as $f) {
                            if ($f->field_type->isAssignable() && array_key_exists($f->field_key, $values)) {
                                $data[$f->field_key] = $values[$f->field_key];
                            }
                        }

                        $batch->reservations()->create([
                            'row_index' => $rowIndex,
                            'certificate_number' => $certificateNumber,
                            'codeword' => $codeword,
                            'recipient_name' => $recipientField ? ($values[$recipientField->field_key] ?? null) : null,
                            'data' => $data,
                            'qr_filename' => $qrFilename,
                            'status' => CertificateBatchReservationStatus::Reserved,
                        ]);

                        $qrFiles[$qrFilename] = $this->qrCodeService->pngBytes(
                            $this->qrCodeService->verificationUrlForCodeword($codeword)
                        );

                        $exportRows[] = array_merge($values, [
                            'certificate_number' => $certificateNumber,
                            'codeword' => $codeword,
                            'qr_image' => $qrFilename,
                        ]);

                        $successCount++;
                    });
                } catch (\Throwable $e) {
                    $errors[] = "Row {$rowNumber}: ".$e->getMessage();
                }
            }
        }

        $failedCount = count($errors);
        $batch->update([
            'successful_rows' => $successCount,
            'failed_rows' => $failedCount,
            'status' => match (true) {
                $successCount === 0 => CertificateBatchStatus::Failed,
                $failedCount === 0 => CertificateBatchStatus::Completed,
                default => CertificateBatchStatus::Partial,
            },
        ]);

        $this->writeReservationExcel($batch, array_merge($headers, ['certificate_number', 'codeword', 'qr_image']), $exportRows);
        $this->writeQrZip($batch, $qrFiles);

        return ['batch' => $batch->refresh(), 'errors' => $errors];
    }

    private function uniqueCodeword(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $codeword = $this->codewordService->generate();
            if ($this->codewordService->isUnique($codeword)
                && ! CertificateBatchReservation::where('codeword', $codeword)->exists()) {
                return $codeword;
            }
        }

        throw new RuntimeException('Could not generate a unique verification codeword after several attempts.');
    }

    /** @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows */
    private function writeReservationExcel(CertificateBatch $batch, array $headers, array $rows): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray($headers, null, 'A1');
        $rowNumber = 2;
        foreach ($rows as $row) {
            $ordered = array_map(fn ($h) => ExcelFormulaGuard::sanitize($row[$h] ?? null), $headers);
            $sheet->fromArray($ordered, null, 'A'.$rowNumber);
            $rowNumber++;
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $path = $this->batchDir($batch).'/reservation.xlsx';
        $absolute = Storage::disk('local')->path($path);
        Storage::disk('local')->makeDirectory($this->batchDir($batch));
        $writer->save($absolute);
    }

    /** @param  array<string, string>  $qrFiles  filename => PNG bytes */
    private function writeQrZip(CertificateBatch $batch, array $qrFiles): void
    {
        $zip = new \ZipArchive;
        $path = $this->batchDir($batch).'/qr-codes.zip';
        $absolute = Storage::disk('local')->path($path);
        Storage::disk('local')->makeDirectory($this->batchDir($batch));

        if ($zip->open($absolute, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the QR codes ZIP archive.');
        }

        foreach ($qrFiles as $filename => $bytes) {
            $zip->addFromString($filename, $bytes);
        }

        $zip->close();
    }

    private function batchDir(CertificateBatch $batch): string
    {
        return "certificate-batches/{$batch->id}";
    }
}
