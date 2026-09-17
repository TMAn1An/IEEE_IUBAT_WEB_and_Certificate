<?php

namespace App\Policies;

use App\Models\User;

/**
 * User management (list/create/edit/activate/deactivate other admins) is
 * Super Admin only — see docs/PROJECT_REQUIREMENTS.md §Roles. A Certificate
 * Manager must get a 403 from the route itself, not just a hidden nav link.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Whether $user may change $model's active status. Blocking the "last
     * active super admin" case happens in the controller (it needs a
     * table-wide count, not just a check on the two model instances this
     * method receives), not here.
     */
    public function toggleActive(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }
}
