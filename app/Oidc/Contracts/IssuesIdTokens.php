<?php

declare(strict_types=1);

namespace App\Oidc\Contracts;

use App\Oidc\Exceptions\SigningKeyUnavailable;

/**
 * Signs the ID Token that tells a client who signed in.
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#IDToken
 */
interface IssuesIdTokens
{
    /**
     * Sign an ID Token.
     *
     * The nonce is the one the client sent to the authorization request, left
     * out when it sent none or the token answers a refresh. The sign-in time is
     * left out when it is not known.
     *
     * @throws SigningKeyUnavailable
     */
    public function issue(IdTokenRequest $request): string;
}
