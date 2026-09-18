<?php

namespace App\Policies;

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
}
