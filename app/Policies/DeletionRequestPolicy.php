<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DeletionRequest;
use App\Models\User;

/**
 * §Authorization: certificate_manager and super_admin may both REQUEST a
 * deletion; only super_admin may REVIEW (approve/reject) one. There is
 * deliberately no `delete()`/`update()` ability defined anywhere on this
 * policy or its controller — a deletion request itself is never edited or
 * removed once created, only transitioned pending -> approved/rejected ->
 * completed by DeletionRequestService.
 */
class DeletionRequestPolicy
{
    /** The full review queue — super_admin only. */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }

    /** A single request's status — the reviewer, or the staff member who requested it (their own request). */
    public function view(User $user, DeletionRequest $deletionRequest): bool
    {
        return $user->role === UserRole::SuperAdmin || $user->id === $deletionRequest->requested_by;
    }

    /** Both admin roles may request a deletion — see CLAUDE.md's two-role boundary. */
    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::CertificateManager], true);
    }

    /** Approve/reject — super_admin only. See §Rejection/Approval flow. */
    public function review(User $user, DeletionRequest $deletionRequest): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }
}
