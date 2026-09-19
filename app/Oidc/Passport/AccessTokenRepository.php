<?php

declare(strict_types=1);

namespace App\Oidc\Passport;

use Laravel\Passport\Bridge\AccessTokenRepository as PassportAccessTokenRepository;
use Laravel\Passport\Passport;

/**
 * Passport's access tokens, carrying the sign-in time across a refresh.
 */
class AccessTokenRepository extends PassportAccessTokenRepository
{
    /**
     * Only a refresh revokes through here, and it does so before storing the
     * new token. The old token's sign-in time is held for the new one, with no
     * nonce, because a refreshed ID Token repeats none.
     */
    public function revokeAccessToken(string $tokenId): void
    {
        $authTime = Passport::token()->newQuery()->whereKey($tokenId)->value('auth_time');

        AuthorizationContext::current()->remember(null, $authTime === null ? null : (int) $authTime);

        parent::revokeAccessToken($tokenId);
    }
}
