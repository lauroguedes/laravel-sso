<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles that apply to the SSO platform itself, as opposed to roles defined
 * by an individual OAuth application.
 */
enum PlatformRole: string
{
    case SuperAdmin = 'Super Admin';

    /**
     * Someone who integrates applications rather than running the server.
     *
     * The role grants no application by itself: it says this person may
     * steward the ones an administrator assigns to them, and reaches nothing
     * else on the platform.
     */
    case Developer = 'Developer';

    /**
     * The platform permissions granted to this role.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => PlatformPermission::values(),
            self::Developer => [PlatformPermission::ApplicationsDevelop->value],
        };
    }
}
