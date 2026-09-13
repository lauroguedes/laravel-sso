<?php

declare(strict_types=1);

namespace App\Oidc\Http\Controllers;

use App\Oidc\Passport\AuthorizationContext;
use Illuminate\Http\Request;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Laravel\Passport\Exceptions\OAuthServerException;
use Laravel\Passport\Http\Controllers\AuthorizationController as PassportAuthorizationController;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passport's authorization endpoint, keeping what the ID Token will repeat.
 *
 * The nonce and the time the user signed in are held for the code this request
 * stores. When Passport shows the consent screen instead, they wait in the
 * session beside its pending authorization until that is approved. Passport
 * keeps one pending authorization at a time, and so does this.
 *
 * It also honours max_age, which Passport does not know about.
 */
class AuthorizationController extends PassportAuthorizationController
{
    /**
     * The session key holding the context of the authorization awaiting consent.
     */
    public const PENDING_SESSION_KEY = 'oidc.pending_authorization';

    public function authorize(
        ServerRequestInterface $psrRequest,
        Request $request,
        ResponseInterface $psrResponse,
        AuthorizationViewResponse $viewResponse,
    ): Response|AuthorizationViewResponse {
        $authTime = $request->session()->get(AuthorizationContext::AUTH_TIME_SESSION_KEY);
        $authTime = is_int($authTime) ? $authTime : null;

        $this->requireRecentSignIn($psrRequest, $request, $authTime);

        $context = AuthorizationContext::current();
        $context->remember($request->filled('nonce') ? $request->string('nonce')->toString() : null, $authTime);
        $awaitingConsent = $request->session()->get('authToken');

        try {
            $response = parent::authorize($psrRequest, $request, $psrResponse, $viewResponse);
        } finally {
            $held = $context->pull();
        }

        if ($request->session()->get('authToken') !== $awaitingConsent) {
            $request->session()->put(self::PENDING_SESSION_KEY, $held);
        }

        return $response;
    }

    /**
     * Send a signed-in user back to sign in when max_age rules out their sign-in.
     *
     * A sign-in older than the client allows, or of no known age, is handled as
     * Passport handles prompt=login, including its guard against asking twice.
     * With prompt=none nobody may be asked, so the client gets login_required
     * (OpenID Connect Core section 3.1.2.1).
     */
    private function requireRecentSignIn(ServerRequestInterface $psrRequest, Request $request, ?int $authTime): void
    {
        $maxAge = $request->query('max_age');

        if (! is_string($maxAge) || ! ctype_digit($maxAge) || $this->guard->guest()) {
            return;
        }

        if ($request->session()->get('promptedForLogin', false)
            || ($authTime !== null && now()->getTimestamp() - $authTime <= (int) $maxAge)) {
            return;
        }

        if ($request->string('prompt')->explode(' ')->contains('none')) {
            throw OAuthServerException::loginRequired($this->withErrorHandling(
                fn (): AuthorizationRequestInterface => $this->server->validateAuthorizationRequest($psrRequest)
            ));
        }

        $this->guard->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->promptForLogin($request);
    }
}
