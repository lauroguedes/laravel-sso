<?php

declare(strict_types=1);

namespace App\Oidc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Oidc\Contracts\EndsSessions;
use App\Oidc\Contracts\LogoutRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the user's session when a relying party signs them out.
 */
class LogoutController extends Controller
{
    /**
     * The parameters a logout request carries, and a confirmation carries back.
     */
    private const PARAMETERS = ['id_token_hint', 'client_id', 'post_logout_redirect_uri', 'state'];

    public function __construct(private readonly EndsSessions $sessions) {}

    /**
     * Handle a logout request.
     *
     * The user is signed out straight away when an id_token_hint this server
     * signed names them, and asked first otherwise, because a link anybody can
     * send must not end a session on its own.
     *
     * A relying party may POST the request from its own page. The browser does
     * not send this server's session cookie with a cross-site POST, so such a
     * request could never see who is signed in. It is turned into the same GET,
     * which the browser sends as a top-level navigation, cookie included.
     */
    public function __invoke(Request $request): Response
    {
        $parameters = array_filter($request->only(self::PARAMETERS), is_string(...));

        if ($request->isMethod('POST')) {
            return redirect()->route('oidc.logout', $parameters, 303);
        }

        $logout = $this->sessions->inspect($request);
        $user = $request->user();

        if ($user === null || $logout->subject === $user->getOidcSubject()) {
            return $this->signOut($request, $logout);
        }

        return Inertia::render('oauth/Logout', [
            'application' => $logout->client === null ? null : ['name' => $logout->client->name],
            'parameters' => $parameters,
        ])->toResponse($request);
    }

    /**
     * Sign out once the user has confirmed.
     */
    public function confirm(Request $request): Response
    {
        return $this->signOut($request, $this->sessions->inspect($request));
    }

    /**
     * End the session here, then send the browser where the client registered.
     *
     * The destination is usually another site, which an Inertia visit can only
     * reach through a full page load, so Inertia::location() answers both kinds
     * of request.
     */
    private function signOut(Request $request, LogoutRequest $logout): Response
    {
        $guard = Auth::guard(config('fortify.guard'));

        if ($guard->check()) {
            $guard->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return Inertia::location($logout->destination ?? route('home'));
    }
}
