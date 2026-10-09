<?php

namespace App\Http\Requests\Admin;

use App\Models\Certificate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class FinalizePdfEditorBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Certificate::class);
    }

    public function rules(): array
    {
        return [
            // The PDF Template Studio editor's own bulk export produces a
            // ZIP of per-row PDFs named by a column the admin chooses
            // (here, the codeword column the reservation step added) — a
            // single certificate's PDF comes back unzipped.
            'package' => ['required', 'file', 'mimes:zip,pdf', 'max:51200'],
        ];
    }

    public function messages(): array
    {
        return [
            'package.mimes' => 'Upload the ZIP (or single PDF) exported from the PDF editor.',
            'package.max' => 'The upload must be 50MB or smaller.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var UploadedFile|null $file */
            $file = $this->file('package');

            if (! $file || ! $file->isValid()) {
                return;
            }

            if ($file->getClientOriginalExtension() === 'pdf') {
                $handle = fopen($file->getRealPath(), 'rb');
                $header = $handle ? fread($handle, 5) : '';
                if ($handle) {
                    fclose($handle);
                }

                if ($header !== '%PDF-') {
                    $validator->errors()->add('package', 'The file does not look like a valid PDF.');
                }
            }
        });
    }
}
