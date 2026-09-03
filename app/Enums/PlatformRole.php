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
     * The platform permissions granted to this role.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => PlatformPermission::values(),
        };
    }
}
