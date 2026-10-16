<?php

namespace App\Http\Requests\Admin;

use App\Models\DeletionRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk sibling of the single-item approve action. There is no separate
 * Form Request for single approve() (the controller authorizes it
 * directly via `$this->authorize('review', $deletionRequest)` against the
 * route-bound model), so this class re-creates that exact same
 * authorization path -- `DeletionRequestPolicy::review()` -- per
 * client-submitted id, inside authorize(), rather than trusting the id
 * list. Never partially authorize a batch: if ANY id does not resolve to
 * a real, reviewable DeletionRequest for this user, authorize() returns
 * false and the whole submission is rejected before anything runs. See
 * docs/CERTIFICATE_SYSTEM.md §Bulk deletion approvals.
 */
class BulkApproveDeletionRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ids = $this->input('deletion_request_ids');

        // Not an array / empty -- let the `required`/`array` rules below
        // produce a normal validation error instead of a 403.
        if (! is_array($ids) || $ids === []) {
            return true;
        }

        $requests = DeletionRequest::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach ($ids as $id) {
            $deletionRequest = $requests->get((int) $id);

            if ($deletionRequest === null || ! $this->user()->can('review', $deletionRequest)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'deletion_request_ids' => ['required', 'array', 'min:1'],
            'deletion_request_ids.*' => ['integer', 'distinct'],
        ];
    }
}
