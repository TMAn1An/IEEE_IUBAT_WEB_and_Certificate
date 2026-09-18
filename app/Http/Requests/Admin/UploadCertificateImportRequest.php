<?php

namespace App\Http\Requests\Admin;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use Illuminate\Foundation\Http\FormRequest;

class UploadCertificateImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CertificateTemplate $template */
        $template = $this->route('template');

        return $this->user()->can('create', Certificate::class)
            && $this->user()->can('view', $template);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // mimes:xlsx checks the extension; ExcelFileReader additionally
            // tries to actually parse the file and rejects anything
            // PhpSpreadsheet can't read as real content-based validation,
            // same "don't trust the extension alone" principle as Phase 4's
            // PDF upload -- see docs/SECURITY.md.
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ];
    }
}
