<?php

namespace App\Http\Requests\Admin;

use App\Enums\CertificateTemplateStatus;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReservePdfEditorBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Certificate::class);
    }

    public function rules(): array
    {
        return [
            'certificate_template_id' => [
                'required',
                Rule::exists(CertificateTemplate::class, 'id')->where('status', CertificateTemplateStatus::Active->value),
            ],
            // Real content sniffing (mimes:) — same pattern as
            // UploadQrImportRequest. A dedicated magic-byte check isn't
            // needed here the way it is for PDFs: PhpSpreadsheet itself
            // fails loudly on a non-spreadsheet file (ExcelFileReader
            // wraps that into a clear error).
            'recipients' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'certificate_template_id.required' => 'Choose a certificate template.',
            'certificate_template_id.exists' => 'Choose an active certificate template.',
            'recipients.mimes' => 'The recipients file must be an .xlsx spreadsheet.',
            'recipients.max' => 'The spreadsheet must be 10MB or smaller.',
        ];
    }
}
