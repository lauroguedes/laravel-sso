<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when a way of staying signed in is taken away.
 *
 * Covers a browser session on this server, a single access token, every token
 * an application holds, and signing a user out of everything — "kind" says
 * which, so the audit trail distinguishes them without four events.
 */
class SessionRevoked
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $kind,
        public readonly ?User $user = null,
        public readonly ?Application $application = null,
        public readonly array $context = [],
    ) {}
}
