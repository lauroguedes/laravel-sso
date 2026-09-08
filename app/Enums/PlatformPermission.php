<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Permissions that govern administration of the SSO platform itself.
 *
 * These are deliberately namespaced with "sso." and kept separate from the
 * roles and permissions that belong to individual OAuth applications: an
 * administrator of this server is not thereby an administrator of every
 * application registered on it.
 *
 * All but one are answers on their own. ApplicationsDevelop is not: it says
 * the holder may administer the applications assigned to them, and grants
 * nothing until an administrator assigns one. Holding it is what lets the
 * whole of somebody's stewardship be withdrawn in a single move, without
 * unpicking every application they were given.
 */
enum PlatformPermission: string
{
    case UsersView = 'sso.users.view';
    case UsersManage = 'sso.users.manage';
    case ApplicationsView = 'sso.applications.view';
    case ApplicationsManage = 'sso.applications.manage';
    case ApplicationsDevelop = 'sso.applications.develop';
    case RolesManage = 'sso.roles.manage';
    case AuditView = 'sso.audit.view';
    case SettingsManage = 'sso.settings.manage';

    /**
     * Every permission value, for seeding and for granting to Super Admin.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
