<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ApplicationUser;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when a user's access to an application is withdrawn. The grant is already deleted; the event carries its last state.
 */
class UserApplicationAccessRevoked
{
    use Dispatchable;

    public function __construct(public readonly ApplicationUser $grant) {}
}
