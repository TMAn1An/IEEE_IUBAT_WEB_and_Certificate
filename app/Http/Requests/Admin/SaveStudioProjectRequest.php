<?php

namespace App\Http\Requests\Admin;

use App\Models\Certificate;
use Illuminate\Foundation\Http\FormRequest;

class SaveStudioProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Certificate::class);
    }

    public function rules(): array
    {
        return [
            // .pdftemplate bundles are ZIPs; real content is validated
            // further by TemplateProjectService (it must contain a parseable
            // project.json) rather than relying on extension/MIME alone.
            'project' => ['required', 'file', 'mimes:zip', 'max:51200'],
            'qr_field_id' => ['required', 'string', 'max:64'],
            'recipient_field_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
