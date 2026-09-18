<?php

namespace App\Services\Certificates\Import;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\TemplateField;
use App\Services\Certificates\TemplateFieldRules;
use App\Services\Certificates\VerificationCodewordService;
use Illuminate\Support\Facades\Validator;

/**
 * Validates a mapped Excel import against a template's fields — the single
 * source of truth both the "preview" and "confirm" steps call, so confirm
 * never trusts what preview merely echoed back through hidden form fields.
 * See docs/CERTIFICATE_SYSTEM.md §Excel import.
 */
class CertificateImportValidator
{
    /**
     * Shared with the public verification route's constraint and
     * VerificationCodewordService::generate()'s own output — see
     * VerificationCodewordService::ACCEPTED_PATTERN's docblock for why one
     * pattern serves all three.
     */
    private const CODEWORD_PATTERN = '/^'.VerificationCodewordService::ACCEPTED_PATTERN.'$/';

    /** Similarly permissive for historical certificate-number formats (slashes, spaces, dots seen in old exports). */
    private const CERTIFICATE_NUMBER_PATTERN = '/^[\w.\/ -]{3,64}$/u';

    /**
     * @param  array<int, string>  $mapping  Excel column index => ImportMappingTarget::* or a template field_key.
     * @return list<string>
     */
    public function validateMapping(CertificateTemplate $template, array $mapping): array
    {
        $errors = [];
        $targets = array_values($mapping);
        $recipientField = $this->recipientField($template);

        if ($recipientField === null) {
            // Reachable here (unlike the live QR-generation flow): import
            // isn't restricted to Active templates, and a template that was
            // never activated may never have had a recipient field set —
            // see docs/CERTIFICATE_SYSTEM.md §Excel import.
            $errors[] = "This category has no recipient name field configured. Set one on \"{$template->name}\" (Templates) before importing.";

            return $errors;
        }

        if (! in_array($recipientField->field_key, $targets, true)) {
            $errors[] = "The recipient name field (\"{$recipientField->label}\") must be mapped before importing.";
        }

        foreach ($template->fields as $field) {
            if (! $field->field_type->isAssignable() || ! $field->is_required || $field->is_recipient_name) {
                continue;
            }

            if (! in_array($field->field_key, $targets, true)) {
                $errors[] = "The required field \"{$field->label}\" must be mapped before importing.";
            }
        }

        return $errors;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|null>>  $rows
     * @param  array<int, string>  $mapping
     * @return list<ImportRowResult>
     */
    public function validateRows(CertificateTemplate $template, array $headers, array $rows, array $mapping): array
    {
        $assignableFields = $template->fields->filter(fn ($f) => $f->field_type->isAssignable())->keyBy('field_key');
        $recipientField = $this->recipientField($template);
        if ($recipientField === null) {
            // validateMapping() is always called first and short-circuits
            // this case (see the controller) -- defensive guard only.
            return [];
        }

        $seenCodewords = Certificate::query()->pluck('codeword')->flip()->all();
        $seenNumbers = Certificate::query()->pluck('certificate_number')->flip()->all();

        $results = [];

        foreach ($rows as $index => $row) {
            $excelRowNumber = $index + 2; // header is row 1
            $errors = [];

            $fieldValues = [];
            foreach ($mapping as $columnIndex => $target) {
                if (! $assignableFields->has($target)) {
                    continue;
                }

                $field = $assignableFields->get($target);
                $value = $row[$columnIndex] ?? null;
                $value = $value === null ? null : trim((string) $value);

                $validator = Validator::make([$target => $value], [$target => TemplateFieldRules::forField($field)]);
                if ($validator->fails()) {
                    $errors[] = "{$field->label}: ".$validator->errors()->first($target);
                } else {
                    $fieldValues[$target] = $value;
                }
            }

            // Only add this generic message when the recipient field's own
            // validation didn't already produce a more specific one above
            // (it always will when the field is required, the normal case
            // -- this only fires for the edge case of a non-required
            // recipient field left genuinely blank).
            $recipientName = $fieldValues[$recipientField->field_key] ?? null;
            if (array_key_exists($recipientField->field_key, $fieldValues) && trim((string) $recipientName) === '') {
                $errors[] = 'Recipient Name missing.';
            }

            $codeword = $this->valueFor($row, $mapping, ImportMappingTarget::CODEWORD);
            $codeword = $codeword !== null ? trim($codeword) : null;
            if ($codeword === '') {
                $codeword = null;
            }
            if ($codeword !== null) {
                if (! preg_match(self::CODEWORD_PATTERN, $codeword)) {
                    $errors[] = 'Invalid codeword format.';
                } elseif (isset($seenCodewords[$codeword])) {
                    $errors[] = 'Duplicate codeword.';
                } else {
                    $seenCodewords[$codeword] = true;
                }
            }

            $certificateNumber = $this->valueFor($row, $mapping, ImportMappingTarget::CERTIFICATE_NUMBER);
            $certificateNumber = $certificateNumber !== null ? trim($certificateNumber) : null;
            if ($certificateNumber === '') {
                $certificateNumber = null;
            }
            if ($certificateNumber !== null) {
                if (! preg_match(self::CERTIFICATE_NUMBER_PATTERN, $certificateNumber)) {
                    $errors[] = 'Invalid certificate number format.';
                } elseif (isset($seenNumbers[$certificateNumber])) {
                    $errors[] = 'Duplicate certificate number.';
                } else {
                    $seenNumbers[$certificateNumber] = true;
                }
            }

            $results[] = new ImportRowResult(
                rowNumber: $excelRowNumber,
                errors: $errors,
                recipientName: $errors === [] ? trim((string) $recipientName) : null,
                fieldValues: $fieldValues,
                codeword: $errors === [] ? $codeword : null,
                certificateNumber: $errors === [] ? $certificateNumber : null,
            );
        }

        return $results;
    }

    private function recipientField(CertificateTemplate $template): ?TemplateField
    {
        return $template->fields->firstWhere('is_recipient_name', true);
    }

    /** @param  list<string|null>  $row */
    private function valueFor(array $row, array $mapping, string $target): ?string
    {
        $columnIndex = array_search($target, $mapping, true);
        if ($columnIndex === false) {
            return null;
        }

        return $row[$columnIndex] ?? null;
    }
}
