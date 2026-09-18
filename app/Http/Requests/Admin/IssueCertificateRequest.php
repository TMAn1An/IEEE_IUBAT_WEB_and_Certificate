<?php

namespace App\Http\Requests\Admin;

use App\Enums\TemplateFieldType;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Builds validation rules dynamically from the selected template's fields —
 * never trusts the browser-rendered form. See
 * docs/CERTIFICATE_SYSTEM.md §Dynamic validation implementation.
 *
 * Submitted shape: fields[{field_key}] = value, one entry per template
 * field. Unknown keys (not matching any of the template's fields) are
 * rejected in withValidator() below, not silently ignored -- CLAUDE.md /
 * the Phase 5 brief both call this out explicitly as an IDOR-adjacent
 * concern (a manipulated field_key must not be able to smuggle a value
 * into a certificate's `data`).
 */
class IssueCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CertificateTemplate $template */
        $template = $this->route('template');

        return $this->user()->can('create', Certificate::class)
            && $this->user()->can('view', $template);
    }

    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('fields'))) {
            $this->merge(['fields' => []]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var CertificateTemplate $template */
        $template = $this->route('template');

        $rules = [
            'fields' => ['array'],
        ];

        foreach ($template->fields as $field) {
            if (! $field->field_type->isAssignable()) {
                continue;
            }

            $key = "fields.{$field->field_key}";
            $fieldRules = [$field->is_required ? 'required' : 'nullable'];

            $fieldRules = array_merge($fieldRules, match ($field->field_type) {
                TemplateFieldType::Text => ['string', 'max:1000'],
                TemplateFieldType::LongText => ['string', 'max:5000'],
                TemplateFieldType::Number => ['numeric'],
                TemplateFieldType::Date => ['date'],
                TemplateFieldType::Dropdown => [Rule::in(
                    collect($field->options ?? [])->filter(fn ($o) => trim((string) $o) !== '')->values()->all()
                )],
                default => ['string', 'max:1000'],
            });

            $rules[$key] = $fieldRules;
        }

        return $rules;
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            /** @var CertificateTemplate $template */
            $template = $this->route('template');

            $knownKeys = $template->fields
                ->filter(fn ($field) => $field->field_type->isAssignable())
                ->pluck('field_key')
                ->all();

            $submittedKeys = array_keys($this->input('fields', []));
            $unknown = array_diff($submittedKeys, $knownKeys);

            if ($unknown !== []) {
                $validator->errors()->add('fields', 'Unrecognized field(s) submitted: '.implode(', ', $unknown));
            }
        });
    }
}
