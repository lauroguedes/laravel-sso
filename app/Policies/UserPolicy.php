<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\User;

/**
 * Authorizes administration of the users of this Identity Provider.
 *
 * Every check goes through a platform permission rather than a role name, so
 * that roles can be reshaped without touching authorization logic.
 */
class UserPolicy
{
    /**
     * Determine whether the administrator can list users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PlatformPermission::UsersView->value);
    }

    /**
     * Determine whether the administrator can view a user.
     */
    public function view(User $user, User $target): bool
    {
        return $user->can(PlatformPermission::UsersView->value);
    }

    /**
     * Determine whether the administrator can create users.
     */
    public function create(User $user): bool
    {
        return $user->can(PlatformPermission::UsersManage->value);
    }

    /**
     * Determine whether the administrator can edit a user.
     */
    public function update(User $user, User $target): bool
    {
        return $user->can(PlatformPermission::UsersManage->value);
    }

    /**
     * Determine whether the administrator can disable or re-enable a user.
     *
     * Administrators may not disable themselves, which would lock them out
     * mid-session and can leave a deployment with no reachable administrator.
     */
    public function updateStatus(User $user, User $target): bool
    {
        return $user->can(PlatformPermission::UsersManage->value)
            && ! $user->is($target);
    }
}
