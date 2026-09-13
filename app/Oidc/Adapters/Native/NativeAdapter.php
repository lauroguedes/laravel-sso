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

/**
 * This project's own OpenID Connect implementation.
 *
 * It is built one port at a time. A port not implemented here yet is lent by
 * the fallback adapter, so choosing this driver never leaves an endpoint
 * without an implementation.
 */
class NativeAdapter implements OidcAdapter
{
    public function __construct(private readonly OidcAdapter $fallback) {}

    public function discovery(): DiscoversProvider
    {
        return $this->fallback->discovery();
    }

    public function signingKeys(): ProvidesSigningKeys
    {
        return $this->fallback->signingKeys();
    }

    public function claims(): ResolvesClaims
    {
        return $this->fallback->claims();
    }

    public function clients(): AuthenticatesClients
    {
        return $this->fallback->clients();
    }

    public function introspection(): IntrospectsTokens
    {
        return $this->fallback->introspection();
    }

    public function revocation(): RevokesTokens
    {
        return $this->fallback->revocation();
    }

    public function sessions(): EndsSessions
    {
        return $this->fallback->sessions();
    }
}
