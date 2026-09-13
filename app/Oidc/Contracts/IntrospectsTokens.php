<?php

declare(strict_types=1);

namespace App\Oidc\Contracts;

use App\Oidc\Exceptions\OAuthError;
use Laravel\Passport\Client;

/**
 * Tells an authenticated client whether a token is active.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc7662
 */
interface IntrospectsTokens
{
    /**
     * Describe a token to the client that asked.
     *
     * A missing, unknown, expired or revoked token is only
     * ['active' => false], so the answer never reveals which it was.
     *
     * @return array<string, mixed>
     *
     * @throws OAuthError when the request cannot be answered
     */
    public function introspect(Client $client, ?string $token, ?string $tokenTypeHint): array;
}
