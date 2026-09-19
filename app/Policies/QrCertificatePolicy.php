<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QrCertificate;
use App\Models\User;

/** Same two-role boundary as everything else in this project — both super_admin and certificate_manager. */
class QrCertificatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, QrCertificate $certificate): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * The read-only "Deleted Records" list — super_admin only. See
     * docs/CERTIFICATE_SYSTEM.md §Admin lists / §Data retention. No
     * `delete()` ability exists anywhere on this policy: a record can only
     * ever be soft-deleted via App\Services\Deletion\DeletionRequestService.
     */
    public function viewDeleted(User $user): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }
}
