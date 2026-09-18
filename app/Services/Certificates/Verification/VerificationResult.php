<?php

namespace App\Services\Certificates\Verification;

/**
 * Everything the public verification page is allowed to render — and
 * nothing else. Deliberately NOT a wrapper around the `Certificate` or
 * `QrCertificate` model: the view only ever sees this DTO, so it is
 * structurally impossible for a Blade template to reach
 * `$certificate->codeword`, `->pdf_path`, `->created_by`, `->data`,
 * `->template_snapshot`, etc. by accident. See
 * docs/CERTIFICATE_SYSTEM.md §Privacy protections.
 *
 * Serves BOTH certificate sources uniformly (the advanced `Certificate`
 * model and the independent simple QR tool's `QrCertificate` model — see
 * §Simple QR tool) without either source knowing about the other:
 * `certificateNumber` is null for a simple QR record (the old tool never
 * had one, and the simple tool doesn't invent one — see
 * docs/CERTIFICATE_SYSTEM.md §Codeword/certificate-number compatibility);
 * `eventName` is null for an advanced-system record (which uses
 * `templateName` instead). The view renders whichever pair is present.
 */
final readonly class VerificationResult
{
    /** @param  list<PublicField>  $publicFields */
    private function __construct(
        public VerificationOutcome $outcome,
        public ?string $certificateNumber = null,
        public ?string $recipientName = null,
        public ?string $templateName = null,
        public ?string $eventName = null,
        public ?string $issuedAt = null,
        public array $publicFields = [],
    ) {}

    /** The advanced system (Certificate, Phase 5 PDF path) — has a certificate number and a template name. */
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

    /** The simple QR tool (QrCertificate) — no certificate number (the old tool never had one), has an event/conference name instead. */
    public static function verifiedSimple(
        string $recipientName,
        ?string $eventName,
        string $issuedAt,
        array $publicFields,
    ): self {
        return new self(
            outcome: VerificationOutcome::Verified,
            recipientName: $recipientName,
            eventName: $eventName,
            issuedAt: $issuedAt,
            publicFields: $publicFields,
        );
    }

    /** Revoked shows only the certificate number (advanced) — see docs/CERTIFICATE_SYSTEM.md §Invalid/revoked state separation. */
    public static function revoked(string $certificateNumber): self
    {
        return new self(outcome: VerificationOutcome::Revoked, certificateNumber: $certificateNumber);
    }

    /** Revoked simple QR record — no certificate number to show, so this variant omits it entirely rather than showing an empty row. */
    public static function revokedSimple(): self
    {
        return new self(outcome: VerificationOutcome::Revoked);
    }

    public static function notFound(): self
    {
        return new self(outcome: VerificationOutcome::NotFound);
    }
}
