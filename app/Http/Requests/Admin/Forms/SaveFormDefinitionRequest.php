<?php

namespace App\Http\Requests\Admin\Forms;

use App\Models\Form;
use App\Services\Forms\FormDefinitionValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The builder's JSON save (Save draft / Publish / autosave). Rules come
 * from FormDefinitionValidator, built per field type from the payload
 * itself; cross-field checks (unique keys, field ownership, condition
 * references, ...) run in its after() hook. The custom-code keys are
 * `prohibited` for anyone but super_admin.
 */
class SaveFormDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetForm());
    }

    public function rules(): array
    {
        return app(FormDefinitionValidator::class)->rules($this->targetForm(), $this->input('fields'), $this->canManageCustomCode());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => app(FormDefinitionValidator::class)->after($v, $this->targetForm(), $this->all()));
    }

    public function messages(): array
    {
        return [
            'custom_css.prohibited' => 'Only a Super Admin can change custom code.',
            'custom_html_before.prohibited' => 'Only a Super Admin can change custom code.',
            'custom_html_after.prohibited' => 'Only a Super Admin can change custom code.',
            'custom_js.prohibited' => 'Only a Super Admin can change custom code.',
            'fields.*.key.regex' => 'Keys must start with a lowercase letter and use only a-z, 0-9 and _ (max 64 characters).',
            'slug.regex' => 'The URL slug may only contain lowercase letters, numbers and single hyphens.',
        ];
    }

    public function targetForm(): Form
    {
        return $this->route('form');
    }

    public function canManageCustomCode(): bool
    {
        return $this->user()->can('manageCustomCode', Form::class);
    }
}
