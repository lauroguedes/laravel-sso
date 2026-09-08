<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\Application;
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

    /**
     * Determine whether the user can read the entries about one application.
     *
     * Narrower than viewAudit, and separately answerable: somebody who stewards
     * an application needs its history to debug their own integration, which is
     * not a reason to hand them every sign-in and every administrative action
     * on this server.
     */
    public function viewForApplication(User $user, Application $application): bool
    {
        return $user->can(PlatformPermission::AuditView->value)
            || $application->isManagedBy($user);
    }
}
