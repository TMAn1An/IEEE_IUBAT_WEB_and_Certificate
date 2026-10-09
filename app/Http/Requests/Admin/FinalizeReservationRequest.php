<?php

namespace App\Http\Requests\Admin;

use App\Models\Certificate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class FinalizeReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Certificate::class);
    }

    public function rules(): array
    {
        return [
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:15360'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var UploadedFile|null $file */
            $file = $this->file('pdf');
            if (! $file || ! $file->isValid()) {
                return;
            }

            $handle = fopen($file->getRealPath(), 'rb');
            $header = $handle ? fread($handle, 5) : '';
            if ($handle) {
                fclose($handle);
            }

            if ($header !== '%PDF-') {
                $validator->errors()->add('pdf', 'The file does not look like a valid PDF.');
            }
        });
    }
}
