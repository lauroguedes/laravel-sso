<?php

declare(strict_types=1);

namespace App\Services;

use Admin9\OidcServer\Contracts\OidcUserInterface;
use Admin9\OidcServer\Services\IdTokenService;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;

/**
 * Tells the claims service which application an ID Token is being minted for.
 *
 * Token generation is the only point that knows the client, but the package
 * does not pass it down to claim resolution. Rather than reimplement the JWT
 * assembly, this establishes the client around the parent call so that
 * ApplicationClaimsService can scope roles and permissions to it.
 */
class ApplicationIdTokenService extends IdTokenService
{
    public function __construct(private readonly ApplicationClaimsService $claims)
    {
        parent::__construct($claims);
    }

    /**
     * Generate an ID Token for the given access token and user.
     */
    public function generateToken(
        AccessTokenEntityInterface $accessToken,
        OidcUserInterface $user,
        ClientEntityInterface $client,
        ?string $nonce = null
    ): string {
        return $this->claims->forClient(
            $client->getIdentifier(),
            fn (): string => parent::generateToken($accessToken, $user, $client, $nonce),
        );
    }
}
