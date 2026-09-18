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
     * @param  array<string, string>  $fieldValues  key => validated value, already confirmed to belong to $category.
     * @param  string|null  $eventName  The conference/event name resolved from the live form
     *                                  selection (per docs/CERTIFICATE_SYSTEM.md §Simple QR tool:
     *                                  old-tool-parity rebuild — the old tool's conference name is
     *                                  chosen per submission, not fixed per category). Falls back
     *                                  to the category's own `event_name` when not given, so other
     *                                  callers (e.g. Excel import) keep working unchanged.
     */
    public function issue(QrCategory $category, array $fieldValues, User $issuedBy, ?string $eventName = null): QrCertificate
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
        $eventName = $eventName !== null && $eventName !== '' ? $eventName : $category->event_name;

        $existing = $this->findDuplicate($category, $fieldValues);
        if ($existing !== null) {
            // Matches the old tool's `record_exists()` exactly: reuse the
            // existing record/codeword rather than create a second row --
            // see docs/CERTIFICATE_SYSTEM.md §Duplicate handling.
            return $existing;
        }

        return DB::transaction(function () use ($category, $fieldValues, $recipientName, $eventName, $issuedBy) {
            $codeword = $this->generateUniqueCodeword();

            return QrCertificate::create([
                'qr_category_id' => $category->id,
                'recipient_name' => $recipientName,
                'event_name' => $eventName,
                'data' => $fieldValues,
                'codeword' => $codeword,
                'status' => QrCertificateStatus::Active,
                'created_by' => $issuedBy->id,
            ]);
        });
    }

    /**
     * Reproduces the old tool's `record_exists()`: an exact,
     * case-insensitive/trimmed match on every submitted field value
     * (which for the seeded BECITHCON category is exactly name+role+
     * session, the old tool's own fixed fields) within the same category.
     * Not scoped to `event_name`/conference — the old tool's check didn't
     * consider conference either (see docs/CERTIFICATE_SYSTEM.md
     * §Duplicate handling for the full reasoning).
     */
    private function findDuplicate(QrCategory $category, array $fieldValues): ?QrCertificate
    {
        $normalized = collect($fieldValues)
            ->map(fn ($value) => trim((string) $value))
            ->sortKeys();

        return QrCertificate::query()
            ->where('qr_category_id', $category->id)
            ->get()
            ->first(function (QrCertificate $candidate) use ($normalized) {
                $candidateData = collect($candidate->data ?? [])
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
