<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Application;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when a disabled application is returned to service.
 */
class ApplicationEnabled
{
    use Dispatchable;

    public function __construct(public readonly Application $application) {}
}
