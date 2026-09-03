<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Records when a user last authenticated, which administrators use to spot
 * dormant accounts. Listening to the framework event covers every route to a
 * session: password, two factor, passkey and remember-me.
 */
class RecordSuccessfulLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill([
            'last_login_at' => $event->user->freshTimestamp(),
        ])->saveQuietly();
    }
}
