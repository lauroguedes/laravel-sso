<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\Application;
use App\Models\User;

/**
 * Authorizes administration of the OAuth2 / OpenID Connect applications
 * registered on this Identity Provider.
 */
class ApplicationPolicy
{
    /**
     * Determine whether the administrator can list applications.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PlatformPermission::ApplicationsView->value);
    }

    /**
     * Determine whether the administrator can view an application.
     */
    public function view(User $user, Application $application): bool
    {
        return $user->can(PlatformPermission::ApplicationsView->value);
    }

    /**
     * Determine whether the administrator can register an application.
     */
    public function create(User $user): bool
    {
        return $user->can(PlatformPermission::ApplicationsManage->value);
    }

    /**
     * Determine whether the administrator can edit an application.
     */
    public function update(User $user, Application $application): bool
    {
        return $user->can(PlatformPermission::ApplicationsManage->value);
    }

    /**
     * Determine whether the administrator can grant or withdraw a user's
     * access to this application.
     *
     * Separate from update() because it changes who can sign in rather than
     * how the application is configured, even though both currently require
     * the same permission.
     */
    public function manageAccess(User $user, Application $application): bool
    {
        return $user->can(PlatformPermission::ApplicationsManage->value);
    }

    /**
     * Determine whether the administrator can regenerate the client secret.
     *
     * A public client has no secret to rotate; PKCE takes its place.
     */
    public function regenerateSecret(User $user, Application $application): bool
    {
        return $user->can(PlatformPermission::ApplicationsManage->value)
            && $application->isConfidential();
    }
}
