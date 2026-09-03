<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Application;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when an administrator registers a new OAuth2 application.
 */
class ApplicationCreated
{
    use Dispatchable;

    public function __construct(public readonly Application $application) {}
}
