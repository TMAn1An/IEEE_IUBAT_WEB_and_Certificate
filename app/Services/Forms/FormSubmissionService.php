<?php

namespace App\Services\Forms;

use App\Enums\FormAvailabilityState;
use App\Enums\FormFieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validates and stores one public submission. Validation rules are built
 * from the stored field definitions on every request -- nothing about the
 * browser's copy of the form (types, options, required flags, visibility)
 * is trusted. See docs/FORM_BUILDER.md §Server-side validation.
 */
class FormSubmissionService
{
    /** Request keys that are framework plumbing, not form data. */
    private const RESERVED_INPUT_KEYS = ['_token', '_method'];

    public function __construct(
        private readonly FormVisibilityResolver $visibility,
        private readonly FormAvailability $availability,
    ) {}

    /**
     * @param  array<string, mixed>  $input  raw request input
     * @param  list<int>  $sessionSubmittedFormIds
     * @param  array<string, mixed>  $metadata
     *
     * @throws ValidationException on invalid input
     * @throws FormUnavailableException when the form can't accept this submission
     */
    public function submit(Form $form, array $input, ?User $submitter, array $sessionSubmittedFormIds = [], array $metadata = []): FormSubmission
    {
        $fields = $form->activeFields()->get();
        $input = array_diff_key($input, array_flip(self::RESERVED_INPUT_KEYS));

        $this->rejectUnknownKeys($fields, $input);

        $visible = $this->visibility->resolve($fields, $input);
        $storable = $fields->filter(fn (FormField $f) => $f->type->collectsValue() && $visible[$f->key] && ! $f->isDisabled());
        $validated = $this->validate($storable, $input);

        return DB::transaction(function () use ($form, $storable, $validated, $submitter, $sessionSubmittedFormIds, $metadata) {
            // Re-checked under a row lock so concurrent submissions can't
            // overshoot the submission limit.
            $locked = Form::query()->lockForUpdate()->findOrFail($form->id);
            $state = $this->availability->check($locked, $submitter, $sessionSubmittedFormIds);
            if ($state !== FormAvailabilityState::Open) {
                throw new FormUnavailableException($state);
            }

            $submission = $locked->submissions()->create([
                'submitted_by' => $submitter?->id,
                'submitted_at' => now(),
                'form_version' => $locked->lock_version,
                'metadata' => $metadata ?: null,
            ]);

            foreach ($storable as $field) {
                $value = $field->usesServerValue() ? $this->serverValue($field) : $this->cleanValue($field, $validated[$field->key] ?? null);

                $submission->values()->create([
                    'form_field_id' => $field->id,
                    'field_key' => $field->key,
                    'field_label_snapshot' => $field->label,
                    'field_type_snapshot' => $field->type->value,
                    'value' => $value,
                    'display_value' => $this->displayValue($field, $value),
                ]);
            }

            return $submission;
        });
    }

    /** @param  Collection<int, FormField>  $fields */
    private function rejectUnknownKeys(Collection $fields, array $input): void
    {
        $allowed = $fields->filter(fn (FormField $f) => $f->type->collectsValue())->pluck('key')->all();
        $unknown = array_diff(array_keys($input), $allowed);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'form' => 'The submission contained fields this form does not have: '.implode(', ', array_slice(array_map('strval', $unknown), 0, 5)).'.',
            ]);
        }
    }

    /**
     * @param  Collection<int, FormField>  $fields  visible, enabled, value-collecting fields
     * @return array<string, mixed>
     */
    private function validate(Collection $fields, array $input): array
    {
        $rules = [];
        $messages = [];
        $attributes = [];

        foreach ($fields as $field) {
            if ($field->usesServerValue()) {
                continue; // value comes from the definition, whatever was posted
            }
            $key = $field->key;
            $attributes[$key] = $field->label;
            $attributes["{$key}.*"] = $field->label;
            $rules += $this->rulesFor($field);

            if ($field->setting('pattern') && $field->setting('pattern_message')) {
                $messages["{$key}.regex"] = $field->setting('pattern_message');
            }
        }

        return Validator::make($input, $rules, $messages, $attributes)->validate();
    }

    /** @return array<string, list<mixed>> */
    private function rulesFor(FormField $field): array
    {
        $key = $field->key;
        $presence = $field->required ? 'required' : 'nullable';
        $min = $field->setting('min');
        $max = $field->setting('max');
        $optionValues = array_map(fn ($o) => (string) $o['value'], $field->options());

        $rules = match ($field->type) {
            FormFieldType::Text => array_filter([$presence, 'string', 'max:'.($field->setting('max_length') ?? 1000),
                $field->setting('pattern') ? 'regex:'.FormDefinitionValidator::patternRegex($field->setting('pattern')) : null]),
            FormFieldType::LongText => [$presence, 'string', 'max:'.($field->setting('max_length') ?? 10000)],
            FormFieldType::Email => [$presence, 'string', 'max:255', 'email:rfc'],
            FormFieldType::Number => array_filter([$presence, 'numeric',
                $min !== null ? 'min:'.$min : null, $max !== null ? 'max:'.$max : null], fn ($r) => $r !== null),
            FormFieldType::Phone => [$presence, 'string', 'max:30', 'regex:/^[0-9+()\-.\s]{3,30}$/'],
            FormFieldType::Date, FormFieldType::Time, FormFieldType::DateTime => array_filter([$presence, 'date_format:'.$field->type->dateFormat(),
                $min !== null ? 'after_or_equal:'.$min : null, $max !== null ? 'before_or_equal:'.$max : null]),
            FormFieldType::Select, FormFieldType::Radio => [$presence, 'string', Rule::in($optionValues)],
            FormFieldType::CheckboxGroup => array_filter([$presence, 'array', $field->required ? 'min:1' : null, 'max:'.count($optionValues)]),
            FormFieldType::Checkbox => $field->required ? ['accepted'] : ['nullable', Rule::in(['1', 'on', 'true', 'yes'])],
            default => [],
        };

        $result = [$key => array_values($rules)];
        if ($field->type === FormFieldType::CheckboxGroup) {
            $result["{$key}.*"] = ['string', 'distinct', Rule::in($optionValues)];
        }

        return $result;
    }

    private function serverValue(FormField $field): mixed
    {
        $default = $field->setting('default_value');

        return $field->type === FormFieldType::Checkbox ? (bool) $default : $default;
    }

    private function cleanValue(FormField $field, mixed $value): mixed
    {
        return match (true) {
            $field->type === FormFieldType::Checkbox => $value !== null && $value !== '',
            $field->type === FormFieldType::CheckboxGroup => array_values(array_map('strval', (array) ($value ?? []))),
            is_string($value) => trim($value) === '' ? null : $value,
            default => $value === null ? null : (string) $value,
        };
    }

    /** Human-readable snapshot: option LABELS, Yes/No, joined lists. */
    private function displayValue(FormField $field, mixed $value): ?string
    {
        if ($field->type === FormFieldType::Checkbox) {
            return $value ? 'Yes' : 'No';
        }
        if ($value === null || $value === []) {
            return null;
        }

        $labels = $field->optionLabels();

        if ($field->type === FormFieldType::CheckboxGroup) {
            return implode(', ', array_map(fn ($v) => $labels[$v] ?? $v, $value));
        }
        if ($field->type->hasOptions()) {
            return $labels[(string) $value] ?? (string) $value;
        }

        return (string) $value;
    }
}
