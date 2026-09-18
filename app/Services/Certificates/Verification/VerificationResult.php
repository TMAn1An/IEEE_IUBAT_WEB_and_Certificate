<?php

namespace App\Services\Certificates\Verification;

/**
 * Everything the public verification page is allowed to render — and
 * nothing else. Deliberately NOT a wrapper around the `Certificate` model:
 * the view only ever sees this DTO, so it is structurally impossible for a
 * Blade template to reach `$certificate->codeword`, `->pdf_path`,
 * `->created_by`, `->data`, `->template_snapshot`, etc. by accident. See
 * docs/CERTIFICATE_SYSTEM.md §Privacy protections.
 */
final readonly class VerificationResult
{
    /** @param  list<PublicField>  $publicFields */
    private function __construct(
        public VerificationOutcome $outcome,
        public ?string $certificateNumber = null,
        public ?string $recipientName = null,
        public ?string $templateName = null,
        public ?string $issuedAt = null,
        public array $publicFields = [],
    ) {}

    public static function verified(
        string $certificateNumber,
        string $recipientName,
        string $templateName,
        string $issuedAt,
        array $publicFields,
    ): self {
        return new self(
            outcome: VerificationOutcome::Verified,
            certificateNumber: $certificateNumber,
            recipientName: $recipientName,
            templateName: $templateName,
            issuedAt: $issuedAt,
            publicFields: $publicFields,
        );
    }

    /** Revoked shows only the certificate number — see docs/CERTIFICATE_SYSTEM.md §Invalid/revoked state separation. */
    public static function revoked(string $certificateNumber): self
    {
        return new self(outcome: VerificationOutcome::Revoked, certificateNumber: $certificateNumber);
    }

    public static function notFound(): self
    {
        return new self(outcome: VerificationOutcome::NotFound);
    }
}
