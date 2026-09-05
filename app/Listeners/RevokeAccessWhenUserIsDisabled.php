<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserDisabled;
use App\Services\SessionManager;

/**
 * Ends every session and revokes every token when a user is disabled.
 *
 * Disabling used to stop somebody signing in while leaving the credentials
 * they already had in circulation: an application holding a live access token
 * could keep calling resource servers on their behalf until it expired, and
 * could keep renewing it with its refresh token. Anyone disabling an account
 * in a hurry had to remember a second action.
 *
 * Applications have always worked this way — ApplicationManager::disable()
 * revokes the tokens it issued, for exactly the same reason — so this makes
 * the two halves of the model agree.
 *
 * A listener rather than a call inside User::disable(), so that every route to
 * disabling an account cascades: the administration interface, a console
 * command, a future bulk action.
 */
class RevokeAccessWhenUserIsDisabled
{
    public function __construct(private readonly SessionManager $sessions) {}

    /**
     * Handle the event.
     */
    public function handle(UserDisabled $event): void
    {
        $this->sessions->revokeEverythingFor($event->user);
    }
}
