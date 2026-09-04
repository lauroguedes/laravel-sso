<?php

declare(strict_types=1);

namespace App\Services;

use Admin9\OidcServer\Contracts\OidcUserInterface;
use Admin9\OidcServer\Services\IdTokenService;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\ClaimsFormatter;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Builder as TokenBuilder;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use RuntimeException;

/**
 * Completes the package's ID Token in two ways.
 *
 * Which application is asking: token generation is the only point that knows
 * the client, but the package does not pass it down to claim resolution.
 * Rather than reimplement the JWT assembly, the client is established around
 * the parent call so ApplicationClaimsService can scope roles and permissions.
 *
 * Which key signed it: the package publishes a "kid" in its JWKS document but
 * never stamps one on a token, so a relying party holding more than one key —
 * during a rotation, say — has no way to choose. The builder is pre-seeded
 * with the header instead, which keeps the parent's assembly untouched.
 */
class ApplicationIdTokenService extends IdTokenService
{
    /**
     * The configuration, once the key id has been seeded into its builder.
     */
    private ?Configuration $stampedConfig = null;

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

    /**
     * The JWT configuration, with every builder stamping the key id.
     *
     * Memoised because this is reached once per token issued, and deriving the
     * key id reads the public key from disk.
     */
    protected function getJwtConfig(): Configuration
    {
        if ($this->stampedConfig !== null) {
            return $this->stampedConfig;
        }

        $configuration = parent::getJwtConfig();

        $keyId = $this->keyId();

        $configuration->setBuilderFactory(
            fn (ClaimsFormatter $formatter): Builder => TokenBuilder::new(new JoseEncoder, $formatter)
                ->withHeader('kid', $keyId)
        );

        return $this->stampedConfig = $configuration;
    }

    /**
     * The identifier published for the signing key.
     *
     * Reproduces the derivation in the OIDC package's JWKS endpoint, which is
     * a protected method on a controller and so cannot be called. Reading the
     * key from the same hard-coded path is deliberate: using
     * Passport::keyPath() here would diverge from the key set whenever the two
     * disagree. KeySetTest pins the token header to the published value.
     */
    private function keyId(): string
    {
        $path = storage_path('oauth-public.key');

        if (! is_file($path)) {
            throw new RuntimeException(
                'The OAuth public key is missing. Run "php artisan passport:keys".'
            );
        }

        return substr(hash('sha256', (string) file_get_contents($path)), 0, 16);
    }
}
