<?php

namespace App\Http\Requests\Admin;

use App\Models\QrCertificate;
use App\Models\QrConferenceOption;
use App\Models\QrConferenceType;
use App\Services\QrTool\QrCategoryService;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Field names deliberately match the old tool's own form exactly
 * (`role_select`, `conference_type`, `conference_select`,
 * `include_conference`, `name`, `include_session`, `session` — see
 * IEEEQRCODEGENERATOR-main/templates/index.html) rather than the generic
 * `fields[{key}]` shape used elsewhere in this project — this page is a
 * deliberate old-tool-parity recreation of one fixed form, not a generic
 * per-category form renderer. See docs/CERTIFICATE_SYSTEM.md §Simple QR
 * tool: old-tool-parity rebuild.
 */
class GenerateQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', QrCertificate::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $category = app(QrCategoryService::class)->primary();
        $roleField = $category?->fields->firstWhere('key', 'role');
        $roleOptions = collect($roleField?->options ?? [])->filter(fn ($o) => trim((string) $o) !== '')->values()->all();

        return [
            'name' => ['required', 'string', 'max:160'],
            'role_select' => ['required', 'string', Rule::in($roleOptions)],
            'include_conference' => ['sometimes', 'boolean'],
            'conference_type' => ['required_if:include_conference,1', 'nullable', 'string', 'max:255'],
            'conference_select' => ['required_if:include_conference,1', 'nullable', 'string', 'max:255'],
            'include_session' => ['sometimes', 'boolean'],
            'session' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'role_select.in' => 'Select a valid role from the list.',
            'conference_type.required_if' => 'Select a conference/event type, or uncheck "Include conference/event".',
            'conference_select.required_if' => 'Select a conference/event name, or uncheck "Include conference/event".',
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            if ($this->boolean('include_conference')) {
                $type = (string) $this->input('conference_type');
                $name = (string) $this->input('conference_select');

                $typeExists = QrConferenceType::query()->where('name', $type)->exists();
                if ($type !== '' && ! $typeExists) {
                    $validator->errors()->add('conference_type', 'Unknown conference/event type.');
                }

                if ($type !== '' && $name !== '' && $typeExists) {
                    $nameExists = QrConferenceOption::query()
                        ->whereHas('type', fn ($q) => $q->where('name', $type))
                        ->where('name', $name)
                        ->exists();
                    if (! $nameExists) {
                        $validator->errors()->add('conference_select', 'Unknown conference/event name for that type.');
                    }
                }
            }
        });
    }
}
