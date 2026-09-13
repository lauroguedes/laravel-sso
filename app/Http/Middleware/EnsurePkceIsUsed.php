<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Exceptions\OAuthServerException as PassportException;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires Proof Key for Code Exchange on every authorization request.
 *
 * The underlying OAuth2 server already requires PKCE of public clients, but
 * leaves it optional for confidential ones. Current OAuth2 security guidance
 * recommends it for both, so "sso.oauth.require_pkce" closes that gap here.
 *
 * Rejecting at the authorization endpoint is enough to cover the exchange as
 * well: once an authorization code carries a challenge, the token endpoint
 * will not redeem it without the matching verifier.
 *
 * Attached to GET /oauth/authorize in "routes/oidc.php".
 */
class EnsurePkceIsUsed
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('sso.oauth.require_pkce')) {
            return $next($request);
        }

        if ($request->filled('code_challenge')) {
            return $next($request);
        }

        /*
         * Raised through the OAuth2 server's own exception so the body, status
         * and headers match every other protocol failure without copying its
         * wording.
         */
        throw new PassportException(
            OAuthServerException::invalidRequest('code_challenge', 'Code challenge must be provided'),
        );
    }
}
