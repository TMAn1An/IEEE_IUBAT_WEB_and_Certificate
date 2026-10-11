<?php

namespace App\Http\Requests\Admin;

use App\Models\CertificateTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The unified "New template" step: name + the demo certificate PDF, in one
 * form — see docs/PDF_STUDIO_INTEGRATION.md's "PDF Certificates entry
 * flow". The PDF validation (mime + magic-header check) is the same rule
 * the old, now-removed designer's background upload used.
 */
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
            // `mimes:pdf` validates real file content via PHP's fileinfo
            // extension (finfo), not the client-supplied Content-Type or
            // filename extension — see docs/SECURITY.md.
            'demo_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and hyphens.',
            'demo_pdf.required' => 'Upload the demo certificate PDF to design from.',
            'demo_pdf.mimes' => 'The file must be a PDF.',
            'demo_pdf.max' => 'The PDF must be 10MB or smaller.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var UploadedFile|null $file */
            $file = $this->file('demo_pdf');

            if (! $file || ! $file->isValid()) {
                return;
            }

            // Belt-and-braces on top of `mimes:pdf`: confirm the actual
            // file bytes start with the PDF magic header.
            $handle = fopen($file->getRealPath(), 'rb');
            $header = $handle ? fread($handle, 5) : '';
            if ($handle) {
                fclose($handle);
            }

            if ($header !== '%PDF-') {
                $validator->errors()->add('demo_pdf', 'The file does not look like a valid PDF.');
            }
        });
    }
}
