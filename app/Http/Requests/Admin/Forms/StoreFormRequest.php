<?php

namespace App\Http\Requests\Admin\Forms;

use App\Models\Form;
use Illuminate\Foundation\Http\FormRequest;

/** "Create form" -- just a name and description; everything else is built in the builder. */
class StoreFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Form::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
