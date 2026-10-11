<?php

namespace App\Http\Requests\Admin;

use App\Models\Certificate;
use Illuminate\Foundation\Http\FormRequest;

class PrepareBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Certificate::class);
    }

    public function rules(): array
    {
        return [
            'participants' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'photos' => ['sometimes', 'array', 'max:500'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png', 'max:'.((int) (config('pdf-studio.max_photo_bytes') / 1024))],
        ];
    }
}
