<?php

namespace App\Http\Requests\Admin;

use App\Models\DeletionRequest;
use Illuminate\Foundation\Http\FormRequest;

class RequestDeletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', DeletionRequest::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
