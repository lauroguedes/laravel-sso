<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Oidc\Passport\AuthorizationContext;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;

/**
 * Records when the user actually signed in, for the auth_time of ID Tokens.
 *
 * Every sign-in route raises Login: password, two factor, passkey and
 * registration. So does restoring a session from the remember me cookie, which
 * is not a sign-in. That one is left unrecorded, so a client asking with
 * max_age for a recent sign-in gets one.
 */
class RecordAuthenticationTime
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $guard = Auth::guard($event->guard);

        if ($guard instanceof SessionGuard && ! $guard->viaRemember()) {
            $guard->getSession()->put(AuthorizationContext::AUTH_TIME_SESSION_KEY, now()->getTimestamp());
        }
    }
}
