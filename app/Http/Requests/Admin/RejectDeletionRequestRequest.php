<?php

namespace App\Http\Requests\Admin;

use App\Models\DeletionRequest;
use Illuminate\Foundation\Http\FormRequest;

class RejectDeletionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DeletionRequest $deletionRequest */
        $deletionRequest = $this->route('deletionRequest');

        return $this->user()->can('review', $deletionRequest);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'review_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
