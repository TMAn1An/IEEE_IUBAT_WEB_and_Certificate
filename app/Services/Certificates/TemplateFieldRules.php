<?php

namespace App\Services\Certificates;

use App\Enums\TemplateFieldType;
use App\Models\TemplateField;
use Illuminate\Validation\Rule;

/**
 * The single source of truth for "what Laravel validation rules does this
 * template field need" — shared by the live issuance form
 * (App\Http\Requests\Admin\IssueCertificateRequest) and the Excel import
 * validator (App\Services\Certificates\Import\CertificateImportValidator),
 * so a field's validation behavior can never drift between the two entry
 * points. See docs/CERTIFICATE_SYSTEM.md §Dynamic validation implementation.
 */
class TemplateFieldRules
{
    /** @return list<mixed> */
    public static function forField(TemplateField $field): array
    {
        $rules = [$field->is_required ? 'required' : 'nullable'];

        return array_merge($rules, match ($field->field_type) {
            TemplateFieldType::Text => ['string', 'max:1000'],
            TemplateFieldType::LongText => ['string', 'max:5000'],
            TemplateFieldType::Number => ['numeric'],
            TemplateFieldType::Date => ['date'],
            TemplateFieldType::Dropdown => [Rule::in(self::dropdownOptions($field))],
            default => ['string', 'max:1000'],
        });
    }

    /** @return list<string> */
    public static function dropdownOptions(TemplateField $field): array
    {
        return collect($field->options ?? [])
            ->filter(fn ($o) => trim((string) $o) !== '')
            ->values()
            ->all();
    }
}
