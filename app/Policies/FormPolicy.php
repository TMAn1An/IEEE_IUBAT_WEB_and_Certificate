<?php

namespace App\Policies;

use App\Models\Form;
use App\Models\User;

/**
 * Form builder authorization -- enforced in controllers/Form Requests,
 * never just by hiding buttons. See docs/FORM_BUILDER.md §Authorization.
 *
 *   both admin roles (super_admin, certificate_manager):
 *       list/view/create/edit/publish/deactivate/duplicate forms,
 *       view and export submissions
 *   super_admin only:
 *       archive/restore forms, and the "Custom Code" section
 *       (custom CSS, custom HTML before/after, stored-only custom JS)
 *
 * Archived forms are read-only for everyone. Nobody can delete a form or a
 * submission: no ability for it exists here, and no route reaches one.
 */
class FormPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Form $form): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Form $form): bool
    {
        return $form->status->isEditable();
    }

    public function publish(User $user, Form $form): bool
    {
        return $form->status->isEditable();
    }

    public function deactivate(User $user, Form $form): bool
    {
        return $form->status->isEditable();
    }

    public function archive(User $user, Form $form): bool
    {
        return $user->isSuperAdmin() && $form->status->isEditable();
    }

    public function restore(User $user, Form $form): bool
    {
        return $user->isSuperAdmin() && ! $form->status->isEditable();
    }

    public function duplicate(User $user, Form $form): bool
    {
        return true;
    }

    public function viewSubmissions(User $user, Form $form): bool
    {
        return true;
    }

    public function export(User $user, Form $form): bool
    {
        return true;
    }

    public function manageCustomCode(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
