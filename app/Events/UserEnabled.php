<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when a disabled user's access is restored.
 */
class UserEnabled
{
    use Dispatchable;

    public function __construct(public readonly User $user) {}
}
