<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\User;
use App\Services\SessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shows and ends the sessions and tokens that keep people signed in.
 *
 * Every action is gated by one named ability, UserPolicy::revokeSessions, so
 * the control shown and the check enforced cannot drift apart. Revoking an
 * application's whole token set is the exception: that belongs to managing the
 * application, and is gated as such.
 */
class SessionController extends Controller
{
    public function __construct(private readonly SessionManager $sessions) {}

    /**
     * List active sessions and issued tokens.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        return Inertia::render('sessions/Index', [
            'tracksSessions' => $this->sessions->tracksSessions(),
            'browserSessions' => $this->sessions->browserSessions($request->session()->getId()),
            'tokens' => $this->sessions->issuedTokens(),
            'canManage' => $request->user()->can('revokeSessions', User::class),
        ]);
    }

    /**
     * End one browser session.
     */
    public function destroy(Request $request, string $session): RedirectResponse
    {
        $this->authorize('revokeSessions', User::class);

        $this->sessions->revokeBrowserSession($session);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Session revoked.')]);

        return back();
    }

    /**
     * Revoke one access token.
     */
    public function destroyToken(string $token): RedirectResponse
    {
        $this->authorize('revokeSessions', User::class);

        $this->sessions->revokeToken($token);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token revoked.')]);

        return back();
    }

    /**
     * Sign a user out everywhere and revoke every token they hold.
     */
    public function destroyForUser(User $user): RedirectResponse
    {
        $this->authorize('revokeSessions', User::class);

        $this->sessions->revokeEverythingFor($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Signed out of every session and application.'),
        ]);

        return back();
    }

    /**
     * Revoke every token an application holds.
     */
    public function destroyForApplication(Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $this->sessions->revokeTokensFor($application);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Application tokens revoked.')]);

        return back();
    }
}
