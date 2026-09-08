<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\Application;
use App\Models\User;

/**
 * Authorizes administration of the OAuth2 / OpenID Connect applications
 * registered on this Identity Provider.
 *
 * Two kinds of person reach these abilities. An administrator holds a platform
 * permission and it answers for every application. Somebody who stewards an
 * application holds nothing platform-wide: their answer is yes for the
 * applications assigned to them and no for the rest, which is why the abilities
 * that take an instance ask it, and the ones that do not stay administrative.
 *
 * Three things are deliberately never a steward's to do. Registering an
 * application decides what exists on this server. Granting access decides who
 * may sign in, which is an access-control decision rather than a configuration
 * one. Enabling and disabling is how an administrator stops a misbehaving
 * application, so letting its steward undo that would make the control
 * meaningless.
 */
class ApplicationPolicy
{
    /**
     * Determine whether the user can list applications.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PlatformPermission::ApplicationsView->value)
            || $user->stewardsApplications();
    }

    /**
     * Determine whether the user can view an application.
     */
    public function view(User $user, Application $application): bool
    {
        return $user->can(PlatformPermission::ApplicationsView->value)
            || $application->isManagedBy($user);
    }

    /**
     * Determine whether the administrator can register an application.
     */
    public function create(User $user): bool
    {
        return $user->can(PlatformPermission::ApplicationsManage->value);
    }

    /**
     * Determine whether the user can edit an application.
     *
     * Covers the roles and permissions its tokens carry, which is the same
     * question — what this application is — asked about a different part of it.
     */
    public function update(User $user, Application $application): bool
    {
        return $user->can(PlatformPermission::ApplicationsManage->value)
            || $application->isManagedBy($user);
    }

    /**
     * Determine whether the administrator can enable or disable an application.
     *
     * Separate from update() because it is how an administrator takes an
     * application out of service. A steward who could re-enable their own
     * would be able to undo that.
     */
    public function changeStatus(User $user, Application $application): bool
    {
        return $user->can(PlatformPermission::ApplicationsManage->value);
    }

    /**
     * Determine whether the administrator can grant or withdraw a user's
     * access to this application.
     *
     * Separate from update() because it changes who can sign in rather than
     * how the application is configured.
     */
    public function manageAccess(User $user, Application $application): bool
    {
        return $user->can(PlatformPermission::ApplicationsManage->value);
    }

    /**
     * Determine whether the user can read who has access to this application.
     *
     * A platform permission and not stewardship: the list is a roll of people,
     * with their names and addresses, and stewarding an application is not a
     * reason to be given its users.
     */
    public function viewAccess(User $user, Application $application): bool
    {
        return $user->can(PlatformPermission::ApplicationsView->value);
    }

    /**
     * Determine whether the administrator can choose who stewards this
     * application.
     *
     * Never a steward's own: it is the assignment that granted them their
     * reach, so letting them extend it would make the assignment self-serving.
     */
    public function manageStewards(User $user, Application $application): bool
    {
        return $user->can(PlatformPermission::ApplicationsManage->value);
    }

    /**
     * Determine whether the user can regenerate the client secret.
     *
     * A public client has no secret to rotate; PKCE takes its place.
     */
    public function regenerateSecret(User $user, Application $application): bool
    {
        return $this->update($user, $application) && $application->isConfidential();
    }
}
