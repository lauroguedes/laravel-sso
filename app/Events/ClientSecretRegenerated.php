<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Application;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when an application's client secret is rotated. The new secret is deliberately not carried on the event: it must never reach a log or an audit record.
 */
class ClientSecretRegenerated
{
    use Dispatchable;

    public function __construct(public readonly Application $application) {}
}
