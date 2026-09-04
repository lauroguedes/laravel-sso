<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Http\Request;

/**
 * Recognises the request that starts an OAuth2 authorization.
 *
 * The OIDC package applies one middleware group to both the /oauth/authorize
 * routes and the /.well-known endpoints, so middleware that belongs only to
 * authorization has to say so itself. Sharing the test keeps the two from
 * drifting apart.
 */
trait IdentifiesAuthorizationRequests
{
    /**
     * Determine whether the request is the one that starts an authorization.
     *
     * Deliberately the GET only. The approval and denial that follow carry no
     * client_id — the client lives in the serialized authorization request in
     * the session — so a policy needing to know the client can only be applied
     * here. Reading that session value would mean depending on Passport's key
     * name and serialization allowlist, and would fail open if either changed.
     *
     * The gate holds regardless: approving requires an auth token that only a
     * GET which passed these checks puts in the session.
     */
    protected function isAuthorizationStart(Request $request): bool
    {
        return $request->isMethod('GET')
            && $request->routeIs('passport.authorizations.authorize');
    }
}
