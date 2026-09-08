<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\User;

/**
 * Authorizes changing how this installation presents itself and what it allows.
 *
 * One ability, because there is only one decision to make: these settings
 * belong to the whole server rather than to any one record, so there is
 * nothing to view, create or delete separately.
 */
class SettingPolicy
{
    /**
     * Determine whether the administrator can change the application settings.
     */
    public function manage(User $user): bool
    {
        return $user->can(PlatformPermission::SettingsManage->value);
    }
}
