<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ApplicationUser;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when a user is given access to an application.
 */
class UserApplicationAccessGranted
{
    use Dispatchable;

    public function __construct(public readonly ApplicationUser $grant) {}
}
