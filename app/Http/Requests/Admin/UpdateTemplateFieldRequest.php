<?php

namespace App\Http\Requests\Admin;

use App\Enums\TemplateFieldType;
use App\Models\TemplateField;
use App\Services\Templates\TemplateFieldService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class UpdateTemplateFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var TemplateField $field */
        $field = $this->route('field');

        return $this->user()->can('update', $field->template);
    }

    public function rules(): array
    {
        /** @var TemplateField $field */
        $field = $this->route('field');

        return [
            'label' => ['required', 'string', 'max:255'],
            'field_key' => [
                'required', 'string', 'max:100',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('template_fields', 'field_key')
                    ->where('certificate_template_id', $field->certificate_template_id)
                    ->ignore($field->id),
            ],
            'field_type' => ['required', new Enum(TemplateFieldType::class), Rule::in(array_map(
                fn (TemplateFieldType $type) => $type->value,
                TemplateFieldType::assignable()
            ))],
            'is_required' => ['sometimes', 'boolean'],
            'show_on_verification' => ['sometimes', 'boolean'],
            'is_recipient_name' => ['sometimes', 'boolean'],
            'verification_label' => ['nullable', 'string', 'max:255'],
            'options' => ['required_if:field_type,dropdown', 'nullable', 'array', 'min:1'],
            'options.*' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
        ];
    }

    public function messages(): array
    {
        return [
            'field_key.regex' => 'The field key must start with a letter and contain only lowercase letters, numbers and underscores.',
            'field_key.unique' => 'That field key is already used by another field on this template.',
            'options.required_if' => 'Dropdown fields need at least one option.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->boolean('is_recipient_name') && $this->input('field_type') !== TemplateFieldType::Text->value) {
                $validator->errors()->add('is_recipient_name', 'Only a text field may be the recipient name field.');
            }

            $fieldKey = (string) $this->input('field_key');
            if ($fieldKey !== '' && ! TemplateFieldService::isValidFieldKey($fieldKey)) {
                $validator->errors()->add('field_key', 'That field key is reserved for system use and cannot be assigned manually.');
            }
        });
    }
}
