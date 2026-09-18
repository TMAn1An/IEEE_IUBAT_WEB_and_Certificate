<?php

namespace App\Http\Requests\Admin;

use App\Models\QrCategory;
use App\Models\QrCertificate;
use Illuminate\Foundation\Http\FormRequest;

class UploadQrImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var QrCategory $category */
        $category = $this->route('category');

        return $this->user()->can('create', QrCertificate::class)
            && $this->user()->can('view', $category);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ];
    }
}
