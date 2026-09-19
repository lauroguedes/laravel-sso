<?php

declare(strict_types=1);

namespace App\Oidc\Contracts;

use App\Oidc\Exceptions\OAuthError;
use Laravel\Passport\Client;

/**
 * Lets a client give up a token it holds.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc7009
 */
interface RevokesTokens
{
    /**
     * Revoke a token issued to the client.
     *
     * A missing or unknown token, or one issued to another client, is not an
     * error: the client learns nothing about tokens that are not its own.
     *
     * @throws OAuthError when the request cannot be answered
     */
    public function revoke(Client $client, ?string $token, ?string $tokenTypeHint): void;
}
