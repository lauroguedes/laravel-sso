<?php

declare(strict_types=1);

namespace App\Oidc\Passport;

use Laravel\Passport\Bridge\AuthCodeRepository as PassportAuthCodeRepository;
use Laravel\Passport\Passport;

/**
 * Passport's authorization codes, handing on what they recorded.
 */
class AuthCodeRepository extends PassportAuthCodeRepository
{
    /**
     * Checked once, when the code is redeemed and before the access token is
     * stored, so what the code recorded is held for that token to record.
     */
    public function isAuthCodeRevoked(string $codeId): bool
    {
        $code = Passport::authCode()->newQuery()
            ->whereKey($codeId)
            ->where('revoked', false)
            ->first(['nonce', 'auth_time']);

        if ($code === null) {
            return true;
        }

        $authTime = $code->getAttribute('auth_time');

        AuthorizationContext::current()->remember($code->getAttribute('nonce'), $authTime === null ? null : (int) $authTime);

        return false;
    }
}
