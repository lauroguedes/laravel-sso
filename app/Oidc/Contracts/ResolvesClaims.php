<?php

declare(strict_types=1);

namespace App\Oidc\Contracts;

/**
 * Turns a user and the scopes they granted into claims.
 */
interface ResolvesClaims
{
    /**
     * The claims a user discloses for the granted scopes.
     *
     * "sub" is always present. Claims that differ between applications, such
     * as roles and permissions, are only included for a known client, and
     * describe that client alone.
     *
     * @param  array<int, string>  $scopes
     * @return array<string, mixed>
     */
    public function claimsFor(OidcUser $user, array $scopes, ?string $clientId): array;

    /**
     * Every claim an ID Token or UserInfo answer may carry, as discovery
     * advertises them.
     *
     * @return array<int, string>
     */
    public function supportedClaims(): array;
}
