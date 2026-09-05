<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\User;

/**
 * Authorizes reading the audit trail.
 *
 * There is deliberately no create, update or delete: entries are written by
 * listeners and removed only by the retention command.
 */
class AuditRecordPolicy
{
    /**
     * Determine whether the administrator can read the audit trail.
     */
    public function viewAudit(User $user): bool
    {
        return $user->can(PlatformPermission::AuditView->value);
    }
}
