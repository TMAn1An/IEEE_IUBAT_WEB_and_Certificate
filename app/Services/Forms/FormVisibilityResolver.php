<?php

namespace App\Services\Forms;

use App\Enums\FormFieldType;
use App\Models\FormField;

/**
 * Server-side twin of public/js/forms/form-logic.js: decides which fields
 * are visible for a given set of submitted values. Submission validation
 * uses this so a field hidden by a condition is never "required", and its
 * value is never stored -- whatever the browser claims. See
 * docs/FORM_BUILDER.md §Conditional logic.
 *
 * Rule shape (form_fields.conditional_rules):
 *   {action: "show"|"hide", match: "all"|"any",
 *    conditions: [{field: <key>, operator: <op>, value: <string>}]}
 *
 * Semantics (must stay identical to the JS):
 *   - fields are evaluated in form order; a condition may only reference
 *     an EARLIER field (enforced at save time), so there are no cycles;
 *   - a hidden field's value counts as empty for later conditions;
 *   - a Section's visibility also applies to every field after it, up to
 *     the next Section;
 *   - string comparisons are trimmed and case-insensitive; for a checkbox
 *     group, `equals`/`contains` mean "one of the ticked options is".
 */
final class FormVisibilityResolver
{
    public const OPERATORS = ['equals', 'not_equals', 'contains', 'is_empty', 'is_not_empty'];

    /**
     * @param  iterable<FormField>  $fields  active fields, in form order
     * @param  array<string, mixed>  $input  submitted values keyed by field key
     * @return array<string, bool> field key => visible
     */
    public function resolve(iterable $fields, array $input): array
    {
        $visible = [];
        $effective = [];
        $sectionVisible = true;

        foreach ($fields as $field) {
            $own = $this->rulesPass($field->conditional_rules, $effective);

            if ($field->type === FormFieldType::Section) {
                $sectionVisible = $own;
                $isVisible = $own;
            } else {
                $isVisible = $sectionVisible && $own;
            }

            $visible[$field->key] = $isVisible;

            if ($field->type->collectsValue()) {
                $effective[$field->key] = $isVisible ? $this->conditionValue($field, $input) : null;
            }
        }

        return $visible;
    }

    /** The value a condition sees: what the visitor sent, or the server-decided default. */
    private function conditionValue(FormField $field, array $input): mixed
    {
        if ($field->usesServerValue() || $field->isDisabled()) {
            $default = $field->setting('default_value');

            return $field->type === FormFieldType::Checkbox ? ($default ? '1' : null) : $default;
        }

        $value = $input[$field->key] ?? null;

        if ($field->type === FormFieldType::Checkbox) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : null;
        }

        return $value;
    }

    /** @param  array<string, mixed>|null  $rules
     * @param  array<string, mixed>  $values */
    public function rulesPass(?array $rules, array $values): bool
    {
        $conditions = $rules['conditions'] ?? [];
        if (! is_array($conditions) || $conditions === []) {
            return true;
        }

        $results = array_map(fn ($condition) => $this->conditionMatches($condition, $values), $conditions);
        $matched = ($rules['match'] ?? 'all') === 'any'
            ? in_array(true, $results, true)
            : ! in_array(false, $results, true);

        return ($rules['action'] ?? 'show') === 'hide' ? ! $matched : $matched;
    }

    /** @param  array<string, mixed>  $condition
     * @param  array<string, mixed>  $values */
    private function conditionMatches(array $condition, array $values): bool
    {
        $actual = $values[$condition['field'] ?? ''] ?? null;
        $target = $this->normalize($condition['value'] ?? '');

        return match ($condition['operator'] ?? null) {
            'is_empty' => $this->isEmpty($actual),
            'is_not_empty' => ! $this->isEmpty($actual),
            'equals' => $this->equals($actual, $target),
            'not_equals' => ! $this->equals($actual, $target),
            'contains' => $this->contains($actual, $target),
            default => false,
        };
    }

    private function equals(mixed $actual, string $target): bool
    {
        if (is_array($actual)) {
            return in_array($target, array_map($this->normalize(...), $actual), true);
        }

        return $this->normalize($actual) === $target;
    }

    private function contains(mixed $actual, string $target): bool
    {
        if (is_array($actual)) {
            return $this->equals($actual, $target);
        }

        return $target === '' || str_contains($this->normalize($actual), $target);
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            return array_filter($value, fn ($item) => $this->normalize($item) !== '') === [];
        }

        return $this->normalize($value) === '';
    }

    private function normalize(mixed $value): string
    {
        return is_scalar($value) ? mb_strtolower(trim((string) $value)) : '';
    }
}
