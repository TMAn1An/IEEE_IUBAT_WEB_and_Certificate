<?php

namespace App\Services\Certificates\Import;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\User;
use App\Services\Certificates\CertificateNumberService;
use App\Services\Certificates\VerificationCodewordService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Writes already-validated import rows to the database. Only ever called
 * with rows `CertificateImportValidator` has already confirmed are valid —
 * see docs/CERTIFICATE_SYSTEM.md §Excel import for the full pipeline.
 *
 * Each row is its own transaction, not one transaction for the whole file:
 * a batch of ~150 historical rows importing 147-good/3-skipped is the
 * expected, normal case here (unlike Phase 5 single-issuance, where any
 * failure should produce nothing at all) — one row hitting a last-moment
 * unique-constraint race (astronomically unlikely given the validator's own
 * DB uniqueness pre-check, but not impossible) should not discard 146 good
 * rows around it.
 */
class CertificateImportService
{
    private const MAX_CODEWORD_ATTEMPTS = 5;

    public function __construct(
        private readonly CertificateNumberService $numbers,
        private readonly VerificationCodewordService $codewords,
    ) {}

    /** @param  list<ImportRowResult>  $validRows */
    public function import(CertificateTemplate $template, array $validRows, User $importedBy): ImportSummary
    {
        $imported = 0;
        $skipped = 0;
        $codewordsPreserved = 0;
        $newCodewordsGenerated = 0;
        $certificateNumbersPreserved = 0;
        $newCertificateNumbersGenerated = 0;
        $failedRowMessages = [];

        foreach ($validRows as $row) {
            try {
                DB::transaction(function () use ($row, $template, $importedBy, &$codewordsPreserved, &$newCodewordsGenerated, &$certificateNumbersPreserved, &$newCertificateNumbersGenerated) {
                    $certificateNumber = $row->certificateNumber;
                    if ($certificateNumber === null) {
                        $certificateNumber = $this->numbers->next();
                        $newCertificateNumbersGenerated++;
                    } else {
                        $certificateNumbersPreserved++;
                    }

                    $codeword = $row->codeword;
                    if ($codeword === null) {
                        $codeword = $this->generateUniqueCodeword();
                        $newCodewordsGenerated++;
                    } else {
                        $codewordsPreserved++;
                    }

                    Certificate::create([
                        'certificate_template_id' => $template->id,
                        'certificate_number' => $certificateNumber,
                        'codeword' => $codeword,
                        'recipient_name' => $row->recipientName,
                        'data' => $row->fieldValues,
                        'pdf_path' => null,
                        'template_snapshot' => null,
                        'layout_snapshot' => null,
                        'status' => CertificateStatus::Active,
                        'issued_at' => now(),
                        'created_by' => $importedBy->id,
                    ]);
                });

                $imported++;
            } catch (Throwable $e) {
                $skipped++;
                $failedRowMessages[] = "Row {$row->rowNumber}: {$e->getMessage()}";
            }
        }

        return new ImportSummary(
            imported: $imported,
            skipped: $skipped,
            codewordsPreserved: $codewordsPreserved,
            newCodewordsGenerated: $newCodewordsGenerated,
            certificateNumbersPreserved: $certificateNumbersPreserved,
            newCertificateNumbersGenerated: $newCertificateNumbersGenerated,
            failedRowMessages: $failedRowMessages,
        );
    }

    private function generateUniqueCodeword(): string
    {
        for ($attempt = 1; $attempt <= self::MAX_CODEWORD_ATTEMPTS; $attempt++) {
            $codeword = $this->codewords->generate();
            if ($this->codewords->isUnique($codeword)) {
                return $codeword;
            }
        }

        throw new RuntimeException('Could not generate a unique verification codeword after '.self::MAX_CODEWORD_ATTEMPTS.' attempts.');
    }
}
