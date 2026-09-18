<?php

namespace App\Services\QrTool;

use App\Enums\QrCertificateStatus;
use App\Models\QrCategory;
use App\Models\QrCertificate;
use App\Models\QrGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The simple QR tool's issuance flow: dynamic form -> DB row -> codeword.
 * No PDF, no snapshot, no certificate number. Mirrors
 * App\Services\Certificates\CertificateIssuanceService in spirit but is a
 * fully independent class operating on QrCategory/QrCertificate --
 * intentionally not sharing code with the advanced system beyond the
 * generic QrCodeService (which knows nothing about either domain model).
 */
class QrCertificateIssuanceService
{
    private const MAX_CODEWORD_ATTEMPTS = 5;

    public function __construct(private readonly QrToolCodewordService $codewords) {}

    /**
     * @param  QrGroup  $group  The auto-resolved Event Type + Event Name +
     *                          Role group this record belongs to (see
     *                          QrGroupService::resolve()) — group-supplied
     *                          `event_name`/`role` are authoritative on the
     *                          created row; the category only supplies the
     *                          field *schema*.
     * @param  array<string, string>  $fieldValues  key => validated value, already confirmed to belong to $category.
     */
    public function issue(QrCategory $category, QrGroup $group, array $fieldValues, User $issuedBy): QrCertificate
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

        $existing = $this->findDuplicate($group, $fieldValues);
        if ($existing !== null) {
            // Matches the old tool's `record_exists()` in spirit: reuse the
            // existing record/codeword rather than create a second row --
            // see docs/CERTIFICATE_SYSTEM.md §Duplicate handling. Scoped to
            // the group (Name + Session) rather than the whole category,
            // since Role is now redundant once a group is fixed.
            return $existing;
        }

        return DB::transaction(function () use ($category, $group, $fieldValues, $recipientName, $issuedBy) {
            $codeword = $this->generateUniqueCodeword();

            return QrCertificate::create([
                'qr_category_id' => $category->id,
                'qr_group_id' => $group->id,
                'recipient_name' => $recipientName,
                'event_name' => $group->event_name,
                'data' => $fieldValues,
                'codeword' => $codeword,
                'status' => QrCertificateStatus::Active,
                'created_by' => $issuedBy->id,
            ]);
        });
    }

    /**
     * Within the SAME group, compares Name + Session only -- Role is
     * excluded from the comparison because it's already guaranteed
     * identical for every record in a group (see docs/CERTIFICATE_SYSTEM.md
     * §Duplicate handling). A different role for the same person resolves
     * to a different group entirely and is therefore never seen here.
     */
    private function findDuplicate(QrGroup $group, array $fieldValues): ?QrCertificate
    {
        $normalized = collect($fieldValues)
            ->except(['role'])
            ->map(fn ($value) => trim((string) $value))
            ->sortKeys();

        return QrCertificate::query()
            ->where('qr_group_id', $group->id)
            ->get()
            ->first(function (QrCertificate $candidate) use ($normalized) {
                $candidateData = collect($candidate->data ?? [])
                    ->except(['role'])
                    ->map(fn ($value) => trim((string) $value))
                    ->sortKeys();

                return $candidateData->count() === $normalized->count()
                    && $candidateData->every(fn ($value, $key) => isset($normalized[$key]) && strcasecmp($value, $normalized[$key]) === 0);
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
