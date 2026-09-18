<?php

namespace App\Http\Requests\Admin;

use App\Models\QrCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQrCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', QrCategory::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'event_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'slug' => [
                'nullable', 'string', 'max:255',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('qr_categories', 'slug'),
            ],
        ];
    }

    public function messages(): array
    {
        return ['slug.regex' => 'The slug may only contain lowercase letters, numbers and hyphens.'];
    }
}
