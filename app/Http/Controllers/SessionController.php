<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\SortsListings;
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
    use SortsListings;

    public function __construct(private readonly SessionManager $sessions) {}

    /**
     * List active sessions and issued tokens.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $sessions = $this->listing($request, 'sessions_');
        $active = $sessions->string('active')->toString() ?: null;

        $tokens = $this->listing($request, 'tokens_');
        $application = $tokens->string('application')->toString() ?: null;

        /*
         * Each listing is a closure, so narrowing one does not run the other's
         * queries: Inertia resolves only the props a partial reload asks for.
         */
        return Inertia::render('sessions/Index', [
            'tracksSessions' => $this->sessions->tracksSessions(),
            'browserSessions' => fn () => $this->sessions->browserSessions(
                $request->session()->getId(),
                $sessions->string('search')->toString() ?: null,
                $active,
                $this->sortedDirection($sessions, ['last_activity']),
                $this->perPage($sessions),
                'sessions_page',
            ),
            'sessionFilters' => [
                'active' => $active,
                ...$this->listingFilters($sessions, ['last_activity']),
            ],
            'tokens' => fn () => $this->sessions->issuedTokens(
                $tokens->string('search')->toString() ?: null,
                $application,
                $this->sortedDirection($tokens, ['expires_at']),
                $this->perPage($tokens),
                'tokens_page',
            ),
            'tokenFilters' => [
                'application' => $application,
                ...$this->listingFilters($tokens, ['expires_at']),
            ],
            'tokenApplications' => fn () => $this->sessions->applicationsHoldingTokens(),
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
        /*
         * changeStatus and not update: this signs out everybody using the
         * application, which is the same kind of decision as taking it out of
         * service, and is deliberately not a steward's to make.
         */
        $this->authorize('changeStatus', $application);

        $this->sessions->revokeTokensFor($application);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Application tokens revoked.')]);

        return back();
    }
}
