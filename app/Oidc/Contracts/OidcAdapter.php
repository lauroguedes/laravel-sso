<?php

declare(strict_types=1);

namespace App\Oidc\Contracts;

/**
 * One implementation of the OpenID Connect layer.
 *
 * Passport is the OAuth 2.0 server, and an adapter supplies what OpenID Connect
 * adds on top of it. Each port is handed out on its own, so an adapter can
 * implement them one at a time and lend the rest from another adapter.
 *
 * Claims are not a port here: which claims a scope discloses and what their
 * values are belong to this application, so every adapter shares them.
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

    /**
     * Logout initiated by a relying party.
     */
    public function sessions(): EndsSessions;
}
