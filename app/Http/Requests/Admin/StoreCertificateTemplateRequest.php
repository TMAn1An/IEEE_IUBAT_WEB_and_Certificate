<?php

namespace App\Http\Requests\Admin;

use App\Models\CertificateTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCertificateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CertificateTemplate::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            // Left blank, the controller auto-generates one from `name`
            // (TemplateService::generateUniqueSlug) — see
            // docs/CERTIFICATE_SYSTEM.md §Template lifecycle.
            'slug' => [
                'nullable', 'string', 'max:255',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('certificate_templates', 'slug'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and hyphens.',
        ];
    }
}
