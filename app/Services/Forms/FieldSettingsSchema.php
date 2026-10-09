<?php

namespace App\Services\Forms;

use App\Enums\FormFieldType;
use Illuminate\Validation\Rule;

/**
 * Validation + normalization for one field's `settings` JSON. Which keys a
 * type may carry comes from FormFieldType::settingKeys(); this class only
 * knows how to validate and clean each key. Unknown keys never survive
 * normalize(), so the stored JSON can't accumulate arbitrary client data.
 */
final class FieldSettingsSchema
{
    public const WIDTHS = [100, 75, 66, 50, 33, 25];

    public const MAX_OPTIONS = 200;

    public const MAX_HTML_LENGTH = 20000;

    /** @return array<string, list<mixed>> */
    public static function rules(FormFieldType $type, string $prefix): array
    {
        $rules = [$prefix => ['nullable', 'array']];

        foreach ($type->settingKeys() as $key) {
            foreach (self::rulesForKey($type, $key, "{$prefix}.{$key}") as $path => $pathRules) {
                $rules[$path] = $pathRules;
            }
        }

        return $rules;
    }

    /** @return array<string, list<mixed>> */
    private static function rulesForKey(FormFieldType $type, string $key, string $path): array
    {
        $dateFormat = $type->dateFormat();
        // min/max mean "numeric bounds" for Number and "date bounds" for the date-ish types.
        $boundRules = match (true) {
            $type === FormFieldType::Number => ['nullable', 'numeric'],
            $dateFormat !== null => ['nullable', 'date_format:'.$dateFormat],
            default => ['nullable', 'string', 'max:255'],
        };
        $defaultValueRules = match (true) {
            $type === FormFieldType::Checkbox => ['nullable', 'boolean'],
            $type === FormFieldType::Number, $dateFormat !== null => $boundRules,
            default => ['nullable', 'string', 'max:1000'],
        };

        return match ($key) {
            'width' => [$path => ['nullable', Rule::in(self::WIDTHS)]],
            'css_class' => [$path => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\- ]*$/']],
            'placeholder', 'pattern_message' => [$path => ['nullable', 'string', 'max:255']],
            'help_text' => [$path => ['nullable', 'string', 'max:1000']],
            'checkbox_text' => [$path => ['nullable', 'string', 'max:500']],
            'show_in_list', 'disabled', 'read_only' => [$path => ['nullable', 'boolean']],
            'default_value' => [$path => $defaultValueRules],
            'min', 'max' => [$path => $boundRules],
            'step' => [$path => ['nullable', 'numeric', 'gt:0']],
            'max_length' => [$path => ['nullable', 'integer', 'min:1', 'max:65535']],
            'rows' => [$path => ['nullable', 'integer', 'min:2', 'max:20']],
            'pattern' => [$path => ['nullable', 'string', 'max:255']],
            'options' => [
                $path => ['required', 'array', 'min:1', 'max:'.self::MAX_OPTIONS],
                "{$path}.*" => ['array'],
                "{$path}.*.label" => ['required', 'string', 'max:255'],
                "{$path}.*.value" => ['required', 'string', 'max:255'],
            ],
            'options_layout' => [$path => ['nullable', Rule::in(['stacked', 'inline'])]],
            'content' => [$path => match ($type) {
                FormFieldType::Html => ['nullable', 'string', 'max:'.self::MAX_HTML_LENGTH],
                FormFieldType::Heading => ['nullable', 'string', 'max:255'],
                default => ['nullable', 'string', 'max:5000'],
            }],
            'heading_level' => [$path => ['nullable', Rule::in(['h2', 'h3', 'h4'])]],
            'text_align' => [$path => ['nullable', Rule::in(['left', 'center', 'right'])]],
        };
    }

    /**
     * Typed, whitelisted copy of already-validated settings. Blank strings
     * and nulls are dropped so stored JSON only holds meaningful values.
     *
     * @return array<string, mixed>
     */
    public static function normalize(FormFieldType $type, ?array $settings): array
    {
        $settings ??= [];
        $clean = [];

        foreach ($type->settingKeys() as $key) {
            if (! array_key_exists($key, $settings)) {
                continue;
            }
            $value = $settings[$key];

            $value = match ($key) {
                'width' => (int) $value,
                'show_in_list', 'disabled', 'read_only' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'max_length', 'rows' => $value === null || $value === '' ? null : (int) $value,
                'options' => self::orderedOptions($value ?? []),
                'default_value' => $type === FormFieldType::Checkbox
                    ? filter_var($value, FILTER_VALIDATE_BOOLEAN)
                    : ($value === null ? null : (string) $value),
                'min', 'max', 'step' => $value === null || $value === '' ? null : (string) $value,
                default => $value,
            };

            // Only meaningful values are stored: blanks, `false` flags and
            // the default 100% width are all implied by their absence.
            if ($value === null || $value === '' || $value === [] || $value === false) {
                continue;
            }
            if ($key === 'width' && $value === 100) {
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * validated() rebuilds nested arrays in rule order, not input order;
     * the numeric keys still carry the admin's order, so sort by them.
     *
     * @return list<array{label: string, value: string}>
     */
    private static function orderedOptions(array $options): array
    {
        ksort($options);

        return array_values(array_map(fn ($option) => [
            'label' => trim((string) $option['label']),
            'value' => trim((string) $option['value']),
        ], $options));
    }
}
