<?php

declare(strict_types=1);

namespace App\Oidc\Contracts;

/**
 * One implementation of the OpenID Connect protocol endpoints.
 *
 * Passport is the OAuth 2.0 server, and an adapter supplies what OpenID Connect
 * adds on top of it. Each port is handed out on its own, so an adapter can
 * implement them one at a time.
 *
 * Claims and logout are not ports here: which claims a user discloses, and when
 * a session may end without asking, are this application's policy, so every
 * adapter shares them.
 */
interface OidcAdapter
{
    /**
     * The discovery document.
     */
    public function discovery(): DiscoversProvider;

    /**
     * The keys that sign ID Tokens.
     */
    public function signingKeys(): ProvidesSigningKeys;

    /**
     * Client authentication at the protocol endpoints.
     */
    public function clients(): AuthenticatesClients;

    /**
     * Token introspection.
     */
    public function introspection(): IntrospectsTokens;

    /**
     * Token revocation.
     */
    public function revocation(): RevokesTokens;
}
