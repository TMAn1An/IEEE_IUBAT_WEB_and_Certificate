<?php

namespace App\Policies;

use App\Models\QrCategory;
use App\Models\User;

/** Same two-role boundary as CertificateTemplatePolicy — both admin roles may manage QR categories. */
class QrCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, QrCategory $category): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, QrCategory $category): bool
    {
        return true;
    }
}
