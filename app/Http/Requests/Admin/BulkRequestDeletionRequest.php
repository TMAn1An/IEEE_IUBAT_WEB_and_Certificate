<?php

namespace App\Http\Requests\Admin;

use App\Models\DeletionRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk sibling of RequestDeletionRequest -- same authorization ability
 * (`create` on DeletionRequest::class, the same role-based create() check
 * every single-item request already uses) and the exact same `reason`
 * validation rule, just applied to a list of certificate ids instead of
 * one route-bound model. See docs/CERTIFICATE_SYSTEM.md §Bulk deletion
 * requests.
 *
 * `certificate_ids.*` must each `exists:certificates,id` against the raw
 * table (so an already soft-deleted id still "exists" here and is instead
 * safely skipped later, at process time, by DeletionRequestService --
 * never a reason to reject the whole batch) -- but any id that isn't a
 * real certificate row at all fails validation and the WHOLE submission is
 * rejected, nothing is applied.
 */
class BulkRequestDeletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', DeletionRequest::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'certificate_ids' => ['required', 'array', 'min:1'],
            'certificate_ids.*' => ['integer', 'distinct', 'exists:certificates,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
