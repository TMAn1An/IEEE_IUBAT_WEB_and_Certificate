<?php

namespace App\Http\Requests\Admin;

use App\Models\QrCertificate;
use Illuminate\Foundation\Http\FormRequest;

class UploadQrImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route parameter is a QrGroup, not a QrCategory -- there is no
        // dedicated QrGroupPolicy (a group has no owner-specific view rules
        // of its own), so this only gates on the same QrCertificate::create
        // ability every other import/generate action uses.
        return $this->user()->can('create', QrCertificate::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ];
    }
}
