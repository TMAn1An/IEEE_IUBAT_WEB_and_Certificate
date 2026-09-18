<?php

namespace App\Services\Certificates\Verification;

use App\Enums\CertificateStatus;
use App\Enums\QrCertificateStatus;
use App\Models\Certificate;
use App\Models\QrCertificate;

/**
 * The one place `GET /certificate/verify/{codeword}` looks anything up —
 * across BOTH independent certificate sources: the simple QR tool
 * (`QrCertificate`) and the advanced system (`Certificate`, Phase 5 PDF
 * path). See docs/CERTIFICATE_SYSTEM.md §Public verification for the full
 * writeup and §Simple QR tool for why these two sources exist separately.
 *
 * Lookup order: `QrCertificate` first, then `Certificate`. Each table
 * enforces `codeword` uniqueness independently (not against each other —
 * see docs/CERTIFICATE_SYSTEM.md §Codeword/certificate-number
 * compatibility for why a true cross-table constraint isn't practical
 * without merging the two intentionally-separate tables, and why the
 * collision risk is accepted as negligible given the two systems'
 * genuinely disjoint codeword formats).
 */
class CertificateVerificationService
{
    public function verify(string $codeword): VerificationResult
    {
        // Exact match only -- never certificate_number, recipient name, or
        // any fuzzy/partial lookup (see docs/CERTIFICATE_SYSTEM.md §Lookup).
        $qrCertificate = QrCertificate::query()->where('codeword', $codeword)->first();
        if ($qrCertificate !== null) {
            return $this->verifySimple($qrCertificate);
        }

        $certificate = Certificate::query()->where('codeword', $codeword)->first();
        if ($certificate !== null) {
            return $this->verifyAdvanced($certificate);
        }

        return VerificationResult::notFound();
    }

    private function verifySimple(QrCertificate $certificate): VerificationResult
    {
        if ($certificate->status === QrCertificateStatus::Revoked) {
            return VerificationResult::revokedSimple();
        }

        if ($certificate->status !== QrCertificateStatus::Active) {
            return VerificationResult::notFound();
        }

        return VerificationResult::verifiedSimple(
            recipientName: $certificate->recipient_name,
            eventName: $certificate->event_name,
            issuedAt: $certificate->created_at?->format('j F Y') ?? '',
            publicFields: $this->simplePublicFields($certificate),
        );
    }

    private function verifyAdvanced(Certificate $certificate): VerificationResult
    {
        if ($certificate->status === CertificateStatus::Revoked) {
            return VerificationResult::revoked($certificate->certificate_number);
        }

        if ($certificate->status !== CertificateStatus::Active) {
            // Reissued (superseded -- the new certificate is the
            // authoritative one now, not this row) or GenerationFailed
            // (should never actually be persisted -- see
            // CertificateIssuanceService) both fall back to the same
            // "Certificate Not Verified" response as an unknown codeword,
            // rather than a fourth public state. Safer default: never
            // claim something is verified unless it's unambiguously so.
            return VerificationResult::notFound();
        }

        return VerificationResult::verified(
            certificateNumber: $certificate->certificate_number,
            recipientName: $certificate->recipient_name ?? '',
            templateName: $this->templateName($certificate),
            issuedAt: $certificate->issued_at?->format('j F Y') ?? '',
            publicFields: $this->publicFields($certificate),
        );
    }

    private function templateName(Certificate $certificate): string
    {
        // Snapshot first -- see docs/CERTIFICATE_SYSTEM.md
        // §Snapshot/current-template fallback logic for the full ordering
        // this method and publicFields() both follow.
        $snapshotName = $certificate->template_snapshot['name'] ?? null;
        if (is_string($snapshotName) && $snapshotName !== '') {
            return $snapshotName;
        }

        return $certificate->template?->name ?? 'IEEE IUBAT Certificate';
    }

    /** @return list<PublicField> */
    private function publicFields(Certificate $certificate): array
    {
        $fieldDefinitions = $this->publicFieldDefinitions($certificate);
        $data = $certificate->data ?? [];

        return $this->buildPublicFields($fieldDefinitions, $data);
    }

    /**
     * Determines which fields are public, and their display labels, in the
     * order documented in docs/CERTIFICATE_SYSTEM.md
     * §Snapshot/current-template fallback logic:
     *
     *   1. `template_snapshot` (Phase 5 PDF certificates) -- frozen at
     *      issuance time, so a later admin edit to the live template can
     *      never silently expose or hide a field on an already-issued
     *      certificate. A snapshot written before this behavior was added
     *      won't have `show_on_verification` recorded at all -- absent is
     *      treated as `false` (hide), the safer default, never `true`.
     *   2. The live template's current fields (a certificate whose
     *      `template_snapshot` is null for any reason).
     *   3. Nothing -- if the template itself can't be resolved (defensive
     *      only; `certificate_template_id` is a `restrictOnDelete` foreign
     *      key, so this shouldn't be reachable in practice), only the
     *      always-shown base fields (certificate number, recipient,
     *      category, date) appear. Privacy wins over convenience.
     *
     * @return list<array{field_key: string, label: string}>
     */
    private function publicFieldDefinitions(Certificate $certificate): array
    {
        $snapshotFields = $certificate->template_snapshot['fields'] ?? null;

        if ($snapshotFields !== null) {
            return collect($snapshotFields)
                // The recipient-flagged field's value is always shown via
                // the dedicated "Recipient" row above -- without this
                // exclusion, a template where that field also has
                // show_on_verification=true would display the same name
                // twice (caught in manual testing).
                ->filter(fn (array $field) => ! ($field['is_recipient_name'] ?? false)
                    && ($field['show_on_verification'] ?? false) === true)
                ->map(fn (array $field) => [
                    'field_key' => $field['field_key'],
                    'label' => $field['verification_label'] ?: $field['label'],
                ])
                ->values()
                ->all();
        }

        $template = $certificate->template;
        if ($template === null) {
            return [];
        }

        return $template->fields
            ->filter(fn ($field) => $field->field_type->isAssignable()
                && ! $field->is_recipient_name
                && $field->show_on_verification)
            ->map(fn ($field) => [
                'field_key' => $field->field_key,
                'label' => $field->verification_label ?: $field->label,
            ])
            ->values()
            ->all();
    }

    /**
     * The simple QR tool's equivalent of publicFieldDefinitions() above --
     * deliberately simpler, since QrCertificate has no snapshot concept at
     * all (see docs/CERTIFICATE_SYSTEM.md §Simple QR tool for why that's an
     * accepted simplification here): always resolved from the category's
     * LIVE fields. A live category's `show_on_verification` toggle
     * therefore does affect the public page of every historical record
     * under it, unlike the advanced system -- a deliberate, documented
     * tradeoff, not an oversight.
     *
     * @return list<PublicField>
     */
    private function simplePublicFields(QrCertificate $certificate): array
    {
        $category = $certificate->category;
        if ($category === null) {
            return [];
        }

        $fieldDefinitions = $category->fields
            ->filter(fn ($field) => ! $field->is_recipient_name && $field->show_on_verification)
            ->map(fn ($field) => ['field_key' => $field->key, 'label' => $field->label])
            ->values()
            ->all();

        return $this->buildPublicFields($fieldDefinitions, $certificate->data ?? []);
    }

    /**
     * @param  list<array{field_key: string, label: string}>  $fieldDefinitions
     * @param  array<string, mixed>  $data
     * @return list<PublicField>
     */
    private function buildPublicFields(array $fieldDefinitions, array $data): array
    {
        $fields = [];
        foreach ($fieldDefinitions as $definition) {
            $value = $data[$definition['field_key']] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $fields[] = new PublicField($definition['label'], (string) $value);
        }

        return $fields;
    }
}
