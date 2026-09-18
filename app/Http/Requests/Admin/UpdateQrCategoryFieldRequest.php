<?php

namespace App\Http\Requests\Admin;

use App\Enums\QrCategoryFieldType;
use App\Models\QrCategoryField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class UpdateQrCategoryFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var QrCategoryField $field */
        $field = $this->route('field');

        return $this->user()->can('update', $field->category);
    }

    public function rules(): array
    {
        /** @var QrCategoryField $field */
        $field = $this->route('field');

        return [
            'label' => ['required', 'string', 'max:255'],
            'key' => [
                'required', 'string', 'max:100',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('qr_category_fields', 'key')
                    ->where('qr_category_id', $field->qr_category_id)
                    ->ignore($field->id),
            ],
            'type' => ['required', new Enum(QrCategoryFieldType::class)],
            'required' => ['sometimes', 'boolean'],
            'show_on_verification' => ['sometimes', 'boolean'],
            'is_recipient_name' => ['sometimes', 'boolean'],
            'options' => ['required_if:type,dropdown', 'nullable', 'array', 'min:1'],
            'options.*' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => 'The field key must start with a letter and contain only lowercase letters, numbers and underscores.',
            'key.unique' => 'That field key is already used by another field on this category.',
            'options.required_if' => 'Dropdown fields need at least one option.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->boolean('is_recipient_name') && $this->input('type') !== QrCategoryFieldType::Text->value) {
                $validator->errors()->add('is_recipient_name', 'Only a text field may be the recipient name field.');
            }
        });
    }
}
