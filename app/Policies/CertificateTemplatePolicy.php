<?php

namespace App\Policies;

use App\Enums\CertificateTemplateStatus;
use App\Models\CertificateTemplate;
use App\Models\User;

/**
 * Unlike user management (Super Admin only), both admin roles may manage
 * certificate templates — see docs/PROJECT_REQUIREMENTS.md §Roles. Written
 * as an explicit policy rather than relying on "there happen to be only two
 * roles right now" so the boundary stays correct and self-documenting if a
 * third, more restricted role is ever added.
 */
class CertificateTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CertificateTemplate $template): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CertificateTemplate $template): bool
    {
        return true;
    }

    /**
     * Whether $user may change $template's background/layout (upload a PDF,
     * move fields/system elements around, save the designer) — same two
     * roles as `update`, but additionally blocked once a template is
     * archived (Phase 4 rule: "archived template should be read-only").
     * The designer's GET/view still uses `update` (an archived template's
     * layout stays visible, just not editable) — this ability only guards
     * the write actions.
     */
    public function manageLayout(User $user, CertificateTemplate $template): bool
    {
        return $template->status !== CertificateTemplateStatus::Archived;
    }
}
