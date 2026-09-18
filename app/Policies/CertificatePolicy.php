<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;

/**
 * Same two-role boundary as CertificateTemplatePolicy — both super_admin and
 * certificate_manager may issue/view/list/download certificates. No
 * revoke/reissue ability yet (Phase 8).
 */
class CertificatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Certificate $certificate): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function download(User $user, Certificate $certificate): bool
    {
        return true;
    }
}
