<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Application;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when an application is disabled and its tokens are revoked.
 */
class ApplicationDisabled
{
    use Dispatchable;

    public function __construct(public readonly Application $application) {}
}
