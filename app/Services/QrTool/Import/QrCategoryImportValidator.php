<?php

namespace App\Services\QrTool\Import;

use App\Models\QrCategory;
use App\Models\QrCertificate;
use App\Services\Certificates\VerificationCodewordService;
use App\Services\QrTool\QrCategoryFieldRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Validates a mapped Excel import against a QR category's fields -- the
 * single source of truth both the preview and confirm steps call. Mirrors
 * App\Services\Certificates\Import\CertificateImportValidator's structure
 * but targets QrCategory/QrCategoryField/QrCertificate; kept as its own
 * class for isolation (see docs/CERTIFICATE_SYSTEM.md §Simple QR tool).
 */
class QrCategoryImportValidator
{
    /** Shared with the public verification route and the advanced system's importer — see VerificationCodewordService::ACCEPTED_PATTERN. */
    private const CODEWORD_PATTERN = '/^'.VerificationCodewordService::ACCEPTED_PATTERN.'$/';

    /** @return list<string> */
    public function validateMapping(QrCategory $category, array $mapping): array
    {
        $errors = [];
        $targets = array_values($mapping);
        $recipientField = $category->fields->firstWhere('is_recipient_name', true);

        if ($recipientField === null) {
            $errors[] = "This QR category has no recipient name field configured. Set one on \"{$category->name}\" (QR Categories) before importing.";

            return $errors;
        }

        if (! in_array($recipientField->key, $targets, true)) {
            $errors[] = "The recipient name field (\"{$recipientField->label}\") must be mapped before importing.";
        }

        foreach ($category->fields as $field) {
            if (! $field->required || $field->is_recipient_name) {
                continue;
            }

            if (! in_array($field->key, $targets, true)) {
                $errors[] = "The required field \"{$field->label}\" must be mapped before importing.";
            }
        }

        return $errors;
    }

    /**
     * @param  list<list<string|null>>  $rows
     * @param  array<int, string>  $mapping
     * @return list<QrImportRowResult>
     */
    public function validateRows(QrCategory $category, array $rows, array $mapping): array
    {
        $fields = $category->fields->keyBy('key');
        $recipientField = $category->fields->firstWhere('is_recipient_name', true);
        if ($recipientField === null) {
            return []; // validateMapping() always runs first and short-circuits this case.
        }

        $seenCodewords = QrCertificate::query()->pluck('codeword')->flip()->all();

        $results = [];

        foreach ($rows as $index => $row) {
            $excelRowNumber = $index + 2;
            $errors = [];

            $fieldValues = [];
            foreach ($mapping as $columnIndex => $target) {
                if (! $fields->has($target)) {
                    continue;
                }

                $field = $fields->get($target);
                $value = $row[$columnIndex] ?? null;
                $value = $value === null ? null : trim((string) $value);

                $validator = Validator::make([$target => $value], [$target => QrCategoryFieldRules::forField($field)]);
                if ($validator->fails()) {
                    $errors[] = "{$field->label}: ".$validator->errors()->first($target);
                } else {
                    $fieldValues[$target] = $value;
                }
            }

            $recipientName = $fieldValues[$recipientField->key] ?? null;
            if (array_key_exists($recipientField->key, $fieldValues) && trim((string) $recipientName) === '') {
                $errors[] = 'Recipient Name missing.';
            }

            $codeword = $this->valueFor($row, $mapping, QrImportMappingTarget::CODEWORD);
            $codeword = $codeword !== null ? trim($codeword) : null;
            $codeword = $codeword === '' ? null : $codeword;
            if ($codeword !== null) {
                if (! preg_match(self::CODEWORD_PATTERN, $codeword)) {
                    $errors[] = 'Invalid codeword format.';
                } elseif (isset($seenCodewords[$codeword])) {
                    $errors[] = 'Duplicate codeword.';
                } else {
                    $seenCodewords[$codeword] = true;
                }
            }

            $eventName = $this->valueFor($row, $mapping, QrImportMappingTarget::EVENT_NAME);
            $eventName = $eventName !== null ? trim($eventName) : null;
            $eventName = $eventName === '' ? null : $eventName;

            $createdAtRaw = $this->valueFor($row, $mapping, QrImportMappingTarget::CREATED_AT);
            $createdAt = null;
            if ($createdAtRaw !== null && trim($createdAtRaw) !== '') {
                try {
                    $createdAt = Carbon::parse(trim($createdAtRaw));
                } catch (\Throwable) {
                    $errors[] = 'Invalid Created At date.';
                }
            }

            $results[] = new QrImportRowResult(
                rowNumber: $excelRowNumber,
                errors: $errors,
                recipientName: $errors === [] ? trim((string) $recipientName) : null,
                fieldValues: $fieldValues,
                codeword: $errors === [] ? $codeword : null,
                eventName: $errors === [] ? $eventName : null,
                createdAt: $errors === [] ? $createdAt : null,
            );
        }

        return $results;
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
