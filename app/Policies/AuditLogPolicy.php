<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Read-only, super_admin-only, by design — see docs/CERTIFICATE_SYSTEM.md
 * §Logbook immutability. This policy deliberately defines ONLY `viewAny()`:
 * there is no `create`/`update`/`delete` ability here because nothing in
 * this codebase ever calls those on AuditLog — not even super_admin has a
 * route that could reach one. Laravel's Gate denies any ability with no
 * matching policy method by default, so this is a genuine, enforced
 * boundary, not just an absent button.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }
}
