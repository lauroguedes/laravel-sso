<?php

declare(strict_types=1);

namespace App\Oidc\Adapters\Native;

/**
 * A token a client presented, once proven genuine, unexpired and not revoked.
 */
class PresentedToken
{
    /**
     * @param  'access_token'|'refresh_token'  $type
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        public readonly string $type,
        public readonly string $accessTokenId,
        public readonly string $clientId,
        public readonly ?string $userId,
        public readonly array $scopes,
        public readonly int $expiresAt,
        public readonly ?int $issuedAt = null,
        public readonly ?string $refreshTokenId = null,
    ) {}
}
