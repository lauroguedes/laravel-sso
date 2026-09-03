<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when a user's ability to authenticate is withdrawn.
 */
class UserDisabled
{
    use Dispatchable;

    public function __construct(public readonly User $user) {}
}
