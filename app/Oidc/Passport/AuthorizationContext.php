<?php

declare(strict_types=1);

namespace App\Oidc\Passport;

/**
 * What the next authorization code or access token stored must record.
 *
 * Scoped to one request, and taken by the first code or token stored after it
 * is set, so nothing held for one can reach another. During the authorization
 * request it holds the nonce and the sign-in time for the code. During the token
 * request it holds what the code, or the token being refreshed, recorded.
 */
class AuthorizationContext
{
    /**
     * The session key holding when the user last actually signed in.
     */
    public const AUTH_TIME_SESSION_KEY = 'oidc.auth_time';

    private ?string $nonce = null;

    private ?int $authTime = null;

    /**
     * The context of the current request.
     *
     * Resolved rather than injected, because Passport keeps the objects that
     * use it for the life of the application.
     */
    public static function current(): self
    {
        return app(self::class);
    }

    /**
     * Hold what the next stored code or token must record.
     */
    public function remember(?string $nonce, ?int $authTime): void
    {
        $this->nonce = $nonce;
        $this->authTime = $authTime;
    }

    /**
     * Take what is held, leaving nothing behind.
     *
     * @return array{nonce: string|null, auth_time: int|null}
     */
    public function pull(): array
    {
        $held = ['nonce' => $this->nonce, 'auth_time' => $this->authTime];

        $this->remember(null, null);

        return $held;
    }
}
