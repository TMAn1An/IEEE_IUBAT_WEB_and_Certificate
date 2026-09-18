<?php

namespace App\Services\QrTool;

use App\Enums\QrCategoryFieldType;
use App\Models\QrCategoryField;
use Illuminate\Validation\Rule;

/**
 * Mirrors App\Services\Certificates\TemplateFieldRules for the simple QR
 * tool's own field type -- kept as a separate class (not a shared/generic
 * one) so the two systems stay fully decoupled, per the isolation
 * requirement in docs/CERTIFICATE_SYSTEM.md §Simple QR tool.
 */
class QrCategoryFieldRules
{
    /** @return list<mixed> */
    public static function forField(QrCategoryField $field): array
    {
        $rules = [$field->required ? 'required' : 'nullable'];

        return array_merge($rules, match ($field->type) {
            QrCategoryFieldType::Text => ['string', 'max:1000'],
            QrCategoryFieldType::LongText => ['string', 'max:2000'], // old tool's session textarea: maxlength="500"; some headroom kept
            QrCategoryFieldType::Number => ['numeric'],
            QrCategoryFieldType::Date => ['date'],
            QrCategoryFieldType::Dropdown => [Rule::in(self::dropdownOptions($field))],
        });
    }

    /** @return list<string> */
    public static function dropdownOptions(QrCategoryField $field): array
    {
        return collect($field->options ?? [])
            ->filter(fn ($o) => trim((string) $o) !== '')
            ->values()
            ->all();
    }
}
