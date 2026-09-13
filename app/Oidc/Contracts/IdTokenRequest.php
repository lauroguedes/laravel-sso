<?php

declare(strict_types=1);

namespace App\Oidc\Contracts;

/**
 * What one ID Token states: who signed in, for which client, and how.
 */
class IdTokenRequest
{
    /**
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        public readonly OidcUser $user,
        public readonly string $clientId,
        public readonly array $scopes,
        public readonly ?string $nonce = null,
        public readonly ?int $authTime = null,
    ) {}
}
