<?php

namespace App\Http\Requests\Admin;

use App\Models\QrCategory;
use App\Models\QrCertificate;
use App\Services\QrTool\QrCategoryFieldRules;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Mirrors App\Http\Requests\Admin\IssueCertificateRequest's structure for
 * the simple QR tool's own field model (QrCategory/QrCategoryField) —
 * built as its own class, not shared, per the isolation requirement.
 * Submitted shape: fields[{key}] = value.
 */
class GenerateQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', QrCertificate::class);
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
        /** @var QrCategory $category */
        $category = $this->route('category');

        $rules = ['fields' => ['array']];

        foreach ($category->fields as $field) {
            $rules["fields.{$field->key}"] = QrCategoryFieldRules::forField($field);
        }

        return $rules;
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            /** @var QrCategory $category */
            $category = $this->route('category');

            $knownKeys = $category->fields->pluck('key')->all();
            $submittedKeys = array_keys($this->input('fields', []));
            $unknown = array_diff($submittedKeys, $knownKeys);

            if ($unknown !== []) {
                $validator->errors()->add('fields', 'Unrecognized field(s) submitted: '.implode(', ', $unknown));
            }
        });
    }
}
