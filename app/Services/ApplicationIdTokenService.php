<?php

declare(strict_types=1);

namespace App\Services;

use Admin9\OidcServer\Contracts\OidcUserInterface;
use Admin9\OidcServer\Services\IdTokenService;
use App\Oidc\Contracts\ProvidesSigningKeys;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\ClaimsFormatter;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder as TokenBuilder;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;

/**
 * Completes the package's ID Token in two ways.
 *
 * Which application is asking: token generation is the only point that knows
 * the client, but the package does not pass it down to claim resolution.
 * Rather than reimplement the JWT assembly, the client is established around
 * the parent call so ApplicationClaimsService can scope roles and permissions.
 *
 * Which key signed it: the key comes from ProvidesSigningKeys, the source
 * Passport and the key set share, rather than the file the package reads. Every
 * token also names it in a "kid" header, so a relying party holding more than
 * one key, during a rotation for example, can choose.
 */
class ApplicationIdTokenService extends IdTokenService
{
    public function __construct(
        private readonly ApplicationClaimsService $claims,
        private readonly ProvidesSigningKeys $keys,
    ) {
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

    /**
     * The JWT configuration, signing with the shared key and stamping its id.
     *
     * Memoised because this is reached once per token issued.
     */
    protected function getJwtConfig(): Configuration
    {
        if ($this->jwtConfig !== null) {
            return $this->jwtConfig;
        }

        $configuration = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText($this->keys->privateKey()),
            InMemory::plainText($this->keys->publicKey()),
        );

        $keyId = $this->keys->keyId();

        $configuration->setBuilderFactory(
            fn (ClaimsFormatter $formatter): Builder => TokenBuilder::new(new JoseEncoder, $formatter)
                ->withHeader('kid', $keyId)
        );

        return $this->jwtConfig = $configuration;
    }
}
