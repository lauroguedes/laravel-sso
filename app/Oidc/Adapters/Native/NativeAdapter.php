<?php

declare(strict_types=1);

namespace App\Oidc\Adapters\Native;

use App\Oidc\Contracts\AuthenticatesClients;
use App\Oidc\Contracts\DiscoversProvider;
use App\Oidc\Contracts\EndsSessions;
use App\Oidc\Contracts\IntrospectsTokens;
use App\Oidc\Contracts\OidcAdapter;
use App\Oidc\Contracts\ProvidesSigningKeys;
use App\Oidc\Contracts\ResolvesClaims;
use App\Oidc\Contracts\RevokesTokens;
use Illuminate\Contracts\Container\Container;

/**
 * This project's own OpenID Connect implementation.
 *
 * It is built one port at a time. A port not implemented here yet is lent by
 * the fallback adapter, so choosing this driver never leaves an endpoint
 * without an implementation.
 *
 * Each port is built when it is asked for. The adapter itself exists from the
 * moment the application boots, so a port can take what it needs in its
 * constructor without that being resolved for every request.
 */
class NativeAdapter implements OidcAdapter
{
    public function __construct(
        private readonly OidcAdapter $fallback,
        private readonly Container $container,
    ) {}

    public function discovery(): DiscoversProvider
    {
        return $this->container->make(Discovery::class);
    }

    public function signingKeys(): ProvidesSigningKeys
    {
        return $this->container->make(SigningKeys::class);
    }

    public function claims(): ResolvesClaims
    {
        return $this->fallback->claims();
    }

    public function clients(): AuthenticatesClients
    {
        return $this->container->make(ClientAuthentication::class);
    }

    public function introspection(): IntrospectsTokens
    {
        return $this->container->make(TokenState::class);
    }

    public function revocation(): RevokesTokens
    {
        return $this->container->make(TokenState::class);
    }

    public function sessions(): EndsSessions
    {
        return $this->fallback->sessions();
    }
}
