<?php

namespace App\Services\QrTool;

use App\Enums\QrCertificateStatus;
use App\Models\QrCategory;
use App\Models\QrCertificate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The simple QR tool's issuance flow: dynamic form -> DB row -> codeword.
 * No PDF, no snapshot, no certificate number. Mirrors
 * App\Services\Certificates\SimpleCertificateService in spirit but is a
 * fully independent class operating on QrCategory/QrCertificate --
 * intentionally not sharing code with the advanced system beyond the
 * generic QrCodeService (which knows nothing about either domain model).
 */
class QrCertificateIssuanceService
{
    private const MAX_CODEWORD_ATTEMPTS = 5;

    public function __construct(private readonly QrToolCodewordService $codewords) {}

    /** @param  array<string, string>  $fieldValues  key => validated value, already confirmed to belong to $category. */
    public function issue(QrCategory $category, array $fieldValues, User $issuedBy): QrCertificate
    {
        if (! $category->is_active) {
            throw ValidationException::withMessages([
                'category' => 'Only active QR categories can be used to generate a QR.',
            ]);
        }

        $recipientField = $category->fields->firstWhere('is_recipient_name', true);
        if ($recipientField === null) {
            // Unreachable via the normal admin UI (QrCategoryFieldService's
            // exclusivity rule plus the "must have a recipient field"
            // check in the generate-QR controller) -- kept as an explicit
            // guard since this is the point the value is actually relied on.
            throw ValidationException::withMessages([
                'category' => 'This QR category has no recipient name field configured.',
            ]);
        }
        $recipientName = (string) ($fieldValues[$recipientField->key] ?? '');

        return DB::transaction(function () use ($category, $fieldValues, $recipientName, $issuedBy) {
            $codeword = $this->generateUniqueCodeword();

            return QrCertificate::create([
                'qr_category_id' => $category->id,
                'recipient_name' => $recipientName,
                'event_name' => $category->event_name,
                'data' => $fieldValues,
                'codeword' => $codeword,
                'status' => QrCertificateStatus::Active,
                'created_by' => $issuedBy->id,
            ]);
        });
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
