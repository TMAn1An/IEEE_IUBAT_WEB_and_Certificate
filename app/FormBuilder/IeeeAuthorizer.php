<?php

namespace App\FormBuilder;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use TMAn1An\FormBuilder\Contracts\Authorizer;

/**
 * Maps IEEE's two admin roles onto the Form + Page Builder package
 * (config/formbuilder.php 'authorizer'). The package's policies call this;
 * no role logic is duplicated in the package.
 *
 *   super_admin          everything, incl. custom code + archive/restore
 *   certificate_manager  build/edit/publish/deactivate/duplicate, submissions, export
 *   inactive account     nothing (the 'active' middleware already blocks the admin)
 */
class IeeeAuthorizer implements Authorizer
{
    public function canAccess(Authenticatable $user): bool
    {
        return $user instanceof User && $user->is_active;
    }

    public function canManageCustomCode(Authenticatable $user): bool
    {
        return $this->canAccess($user) && $user->isSuperAdmin();
    }

    public function canArchive(Authenticatable $user): bool
    {
        return $this->canAccess($user) && $user->isSuperAdmin();
    }
}
