<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Application;
use Closure;
use Illuminate\Http\Request;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Decides where a client may send the browser after RP-initiated logout.
 *
 * The OIDC package matches "post_logout_redirect_uri" against the client's
 * redirect URIs by scheme, host, port and path prefix. That is looser than it
 * looks: any path under a registered redirect URI is accepted, and the list it
 * consults is the one that receives authorization codes.
 *
 * This requires an exact match against a list registered for that purpose,
 * and performs the redirect itself once the package has ended the session.
 *
 * An unapproved destination is dropped rather than refused: the user asked to
 * be logged out, and they are, whatever the client got wrong about where to
 * send them next. Refusing the request would leave the session alive over a
 * redirect mistake, which is the more dangerous failure.
 *
 * Attached to the logout route in "routes/oidc.php".
 */
class ValidatePostLogoutRedirect
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $destination = $this->approvedDestination($request);

        /*
         * Removed whether or not it was approved, so that the package's own,
         * looser matching never runs: it would otherwise accept any path
         * beneath a registered redirect URI. This list is the only one that
         * decides, in both directions.
         */
        $request->query->remove('post_logout_redirect_uri');

        $response = $next($request);

        return $destination === null ? $response : redirect()->away($destination);
    }

    /**
     * The URI this request may be sent to afterwards, if any.
     *
     * "state" is echoed back when the client sent one, which is how it ties
     * the landing page to the logout it started.
     */
    private function approvedDestination(Request $request): ?string
    {
        $uri = $request->query('post_logout_redirect_uri');

        if (! is_string($uri) || $uri === '' || ! $this->isRegistered($request, $uri)) {
            return null;
        }

        $state = $request->query('state');

        if (! is_string($state) || $state === '') {
            return $uri;
        }

        return $uri.(str_contains($uri, '?') ? '&' : '?').http_build_query(['state' => $state]);
    }

    /**
     * Determine whether the client that issued the hint registered this URI.
     */
    private function isRegistered(Request $request, string $uri): bool
    {
        $application = $this->applicationFromHint($request);

        return $application instanceof Application && $application->permitsLogoutRedirect($uri);
    }

    /**
     * The client named by "id_token_hint", if it names one this server knows.
     *
     * The hint is parsed without verifying its signature, as the specification
     * allows: it only selects whose allowlist to consult, and the allowlist is
     * the security boundary. A forged hint can therefore only reach URIs that
     * the named client itself registered.
     */
    private function applicationFromHint(Request $request): ?Application
    {
        $hint = $request->query('id_token_hint');

        if (! is_string($hint) || $hint === '') {
            return null;
        }

        try {
            $token = (new Parser(new JoseEncoder))->parse($hint);
        } catch (Throwable) {
            return null;
        }

        if (! $token instanceof Plain) {
            return null;
        }

        $audience = $token->claims()->get('aud');
        $clientId = is_array($audience) ? ($audience[0] ?? null) : $audience;

        if (! is_string($clientId) || $clientId === '') {
            return null;
        }

        return Application::query()->find($clientId);
    }
}
