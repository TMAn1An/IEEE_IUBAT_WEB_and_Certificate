<?php

namespace App\Services\QrTool\Import;

use App\Enums\QrCertificateStatus;
use App\Models\QrCategory;
use App\Models\QrCertificate;
use App\Models\QrGroup;
use App\Models\User;
use App\Services\QrTool\QrToolCodewordService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Writes already-validated QR-tool import rows to the database. One
 * transaction per row (partial import is intentional) -- see
 * App\Services\Certificates\Import\CertificateImportService's docblock for
 * the same reasoning, mirrored here for the simple-tool's own table.
 *
 * Every imported row belongs to the given destination QrGroup: `event_name`
 * and `data['role']` are always taken from the group (see
 * QrCategoryImportValidator, which already forces this on `$row->fieldValues`),
 * never from the file.
 */
class QrCategoryImportService
{
    private const MAX_CODEWORD_ATTEMPTS = 5;

    public function __construct(private readonly QrToolCodewordService $codewords) {}

    /** @param  list<QrImportRowResult>  $validRows */
    public function import(QrCategory $category, QrGroup $group, array $validRows, User $importedBy): QrImportSummary
    {
        $imported = 0;
        $skipped = 0;
        $codewordsPreserved = 0;
        $newCodewordsGenerated = 0;
        $failedRowMessages = [];

        foreach ($validRows as $row) {
            try {
                DB::transaction(function () use ($row, $category, $group, $importedBy, &$codewordsPreserved, &$newCodewordsGenerated) {
                    $codeword = $row->codeword;
                    if ($codeword === null) {
                        $codeword = $this->generateUniqueCodeword();
                        $newCodewordsGenerated++;
                    } else {
                        $codewordsPreserved++;
                    }

                    $certificate = QrCertificate::create([
                        'qr_category_id' => $category->id,
                        'qr_group_id' => $group->id,
                        'recipient_name' => $row->recipientName,
                        'event_name' => $group->event_name,
                        'data' => $row->fieldValues,
                        'codeword' => $codeword,
                        'status' => QrCertificateStatus::Active,
                        'created_by' => $importedBy->id,
                    ]);

                    if ($row->createdAt !== null) {
                        // Preserve the original registration date from the
                        // old tool's "Created At" column -- see
                        // docs/CERTIFICATE_SYSTEM.md §Excel import for why
                        // this is done as an explicit post-create override
                        // rather than fighting Eloquent's automatic
                        // timestamp management during create().
                        $certificate->timestamps = false;
                        $certificate->created_at = $row->createdAt;
                        $certificate->save();
                    }
                });

                $imported++;
            } catch (Throwable $e) {
                $skipped++;
                $failedRowMessages[] = "Row {$row->rowNumber}: {$e->getMessage()}";
            }
        }

        return new QrImportSummary(
            imported: $imported,
            skipped: $skipped,
            codewordsPreserved: $codewordsPreserved,
            newCodewordsGenerated: $newCodewordsGenerated,
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

        throw new RuntimeException('Could not generate a unique QR codeword after '.self::MAX_CODEWORD_ATTEMPTS.' attempts.');
    }
}
