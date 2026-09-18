<?php

namespace App\Services\Certificates;

use App\Enums\CertificateStatus;
use App\Enums\CertificateTemplateStatus;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Phase 6: the simplified "generate a codeword + QR, admin places it into
 * Canva by hand" workflow — no PDF is rendered or stored. Deliberately a
 * separate, much smaller service from `CertificateIssuanceService` (Phase
 * 5's PDF-designer path) rather than adding a "skip the PDF" flag to it:
 * the two flows share the number/codeword sub-services but have almost
 * nothing else in common (no background/coordinate/font handling here at
 * all), and Phase 5's PDF designer work is explicitly paused, not removed —
 * see docs/CERTIFICATE_SYSTEM.md §Simplified QR workflow.
 */
class SimpleCertificateService
{
    public function __construct(
        private readonly CertificateNumberService $numbers,
        private readonly VerificationCodewordService $codewords,
    ) {}

    private const MAX_CODEWORD_ATTEMPTS = 5;

    /** @param  array<string, string>  $fieldValues  field_key => validated value, already confirmed to belong to $template. */
    public function issue(CertificateTemplate $template, array $fieldValues, User $issuedBy): Certificate
    {
        if ($template->status !== CertificateTemplateStatus::Active) {
            throw ValidationException::withMessages([
                'template' => 'Only active templates can be used to issue certificates.',
            ]);
        }

        $recipientField = $template->fields->firstWhere('is_recipient_name', true);
        if ($recipientField === null) {
            // Unreachable in practice -- TemplateService::activationErrors()
            // blocks activation without exactly one recipient-name field.
            throw ValidationException::withMessages([
                'template' => 'Template has no recipient name field configured.',
            ]);
        }
        $recipientName = (string) ($fieldValues[$recipientField->field_key] ?? '');

        return DB::transaction(function () use ($template, $fieldValues, $recipientName, $issuedBy) {
            $certificateNumber = $this->numbers->next();
            $codeword = $this->generateUniqueCodeword();

            return Certificate::create([
                'certificate_template_id' => $template->id,
                'certificate_number' => $certificateNumber,
                'codeword' => $codeword,
                'recipient_name' => $recipientName,
                'data' => $fieldValues,
                'pdf_path' => null,
                'template_snapshot' => null,
                'layout_snapshot' => null,
                'status' => CertificateStatus::Active,
                'issued_at' => now(),
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

        throw new RuntimeException('Could not generate a unique verification codeword after '.self::MAX_CODEWORD_ATTEMPTS.' attempts.');
    }
}
