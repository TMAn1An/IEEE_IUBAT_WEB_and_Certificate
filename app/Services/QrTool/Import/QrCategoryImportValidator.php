<?php

namespace App\Services\QrTool\Import;

use App\Models\QrCategory;
use App\Models\QrCertificate;
use App\Models\QrGroup;
use App\Services\Certificates\VerificationCodewordService;
use App\Services\QrTool\QrCategoryFieldRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Validates a mapped Excel import against a QR category's field schema AND
 * a destination QrGroup -- the single source of truth both the preview and
 * confirm steps call. See docs/CERTIFICATE_SYSTEM.md §Simple QR tool:
 * group-based import.
 *
 * `role` is never taken from the row: the destination group is
 * authoritative (see the `qr_groups` migration and QrGroupService), so
 * every produced fieldValues['role'] is forced to the group's own role
 * regardless of what (if anything) a "Role" column contained. A "Role"/
 * "Conference" column, if mapped, is used only to double-check the file
 * actually belongs to the selected group (see validateRows()).
 */
class QrCategoryImportValidator
{
    /** Shared with the public verification route and the advanced system's importer — see VerificationCodewordService::ACCEPTED_PATTERN. */
    private const CODEWORD_PATTERN = '/^'.VerificationCodewordService::ACCEPTED_PATTERN.'$/';

    /** Marks a row skipped because it matches a record already in the destination group -- see validateRows(). */
    public const DUPLICATE_ERROR = 'Duplicate existing record (matches an existing entry in this group).';

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
            // Role is required on the category's live form, but never
            // mapped here -- it always comes from the destination group.
            if (! $field->required || $field->is_recipient_name || $field->key === 'role') {
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
    public function validateRows(QrCategory $category, QrGroup $group, array $rows, array $mapping): array
    {
        $fields = $category->fields->where('key', '!=', 'role')->keyBy('key');
        $recipientField = $category->fields->firstWhere('is_recipient_name', true);
        if ($recipientField === null) {
            return []; // validateMapping() always runs first and short-circuits this case.
        }

        $seenCodewords = QrCertificate::query()->pluck('codeword')->flip()->all();

        // Existing records already in the destination group, keyed by
        // Name+Session -- equivalent to the old tool's Name+Role+Session
        // check since Role is fixed by the group. Extended with rows from
        // this same file as they're validated, so two matching rows in one
        // file are also caught (not just matches against the DB).
        $seenInGroup = $group->certificates()
            ->get(['recipient_name', 'data'])
            ->map(fn (QrCertificate $c) => $this->duplicateKey($c->recipient_name, (string) ($c->data['session'] ?? '')))
            ->flip()
            ->all();

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

            // Group-consistency validation: the row's own Conference/Role
            // columns (if mapped) must not silently import against a
            // different group -- see docs/CERTIFICATE_SYSTEM.md §Simple QR
            // tool: group-based import.
            $rowRole = $this->valueFor($row, $mapping, QrImportMappingTarget::ROLE_VALIDATE);
            if ($rowRole !== null && trim($rowRole) !== '' && strcasecmp(trim($rowRole), $group->role) !== 0) {
                $errors[] = 'Role "'.trim($rowRole)."\" does not match destination group \"{$group->role}\".";
            }

            $rowConference = $this->valueFor($row, $mapping, QrImportMappingTarget::CONFERENCE_VALIDATE);
            if ($rowConference !== null && trim($rowConference) !== '' && strcasecmp(trim($rowConference), $group->event_name) !== 0) {
                $errors[] = 'Conference "'.trim($rowConference)."\" does not match destination group \"{$group->event_name}\".";
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

            $createdAtRaw = $this->valueFor($row, $mapping, QrImportMappingTarget::CREATED_AT);
            $createdAt = null;
            if ($createdAtRaw !== null && trim($createdAtRaw) !== '') {
                try {
                    $createdAt = Carbon::parse(trim($createdAtRaw));
                } catch (\Throwable) {
                    $errors[] = 'Invalid Created At date.';
                }
            }

            // The destination group is authoritative for Role -- never the
            // row's own value, even if a "Role" column happened to get
            // mapped to the category's role field by mistake.
            $fieldValues['role'] = $group->role;

            if ($errors === [] && $recipientName !== null) {
                $dupKey = $this->duplicateKey((string) $recipientName, (string) ($fieldValues['session'] ?? ''));
                if (isset($seenInGroup[$dupKey])) {
                    $errors[] = self::DUPLICATE_ERROR;
                } else {
                    $seenInGroup[$dupKey] = true;
                }
            }

            $results[] = new QrImportRowResult(
                rowNumber: $excelRowNumber,
                errors: $errors,
                recipientName: $errors === [] ? trim((string) $recipientName) : null,
                fieldValues: $fieldValues,
                codeword: $errors === [] ? $codeword : null,
                createdAt: $errors === [] ? $createdAt : null,
            );
        }

        return $results;
    }

    private function duplicateKey(string $name, string $session): string
    {
        return Str::lower(trim($name)).'|'.Str::lower(trim($session));
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
