<?php

namespace App\Http\Requests\Admin;

use App\Models\QrCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQrCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('category'));
    }

    public function rules(): array
    {
        /** @var QrCategory $category */
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:255'],
            'event_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'slug' => [
                'required', 'string', 'max:255',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('qr_categories', 'slug')->ignore($category->id),
            ],
        ];
    }

    public function messages(): array
    {
        return ['slug.regex' => 'The slug may only contain lowercase letters, numbers and hyphens.'];
    }
}
