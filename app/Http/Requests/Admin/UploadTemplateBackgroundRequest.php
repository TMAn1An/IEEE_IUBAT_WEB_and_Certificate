<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class UploadTemplateBackgroundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageLayout', $this->route('template'));
    }

    public function rules(): array
    {
        return [
            // `mimes:pdf` validates real file content via PHP's fileinfo
            // extension (finfo), not the client-supplied Content-Type or
            // filename extension — see docs/SECURITY.md. No FPDI/Imagick
            // dependency needed for this check; the designer never asks the
            // server to parse the PDF's internal structure (see
            // docs/CERTIFICATE_SYSTEM.md §Background handling).
            'background' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'background.mimes' => 'The file must be a PDF.',
            'background.max' => 'The PDF must be 10MB or smaller.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var UploadedFile|null $file */
            $file = $this->file('background');

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
                $validator->errors()->add('background', 'The file does not look like a valid PDF.');
            }
        });
    }
}
