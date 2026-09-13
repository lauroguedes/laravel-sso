<?php

declare(strict_types=1);

namespace App\Oidc\Contracts;

/**
 * Somebody who signs in to applications through this provider.
 *
 * The user supplies the value of each claim. Which claims a client receives is
 * decided by ResolvesClaims, not by the user.
 */
interface OidcUser
{
    /**
     * The stable identifier relying parties know this user by.
     */
    public function getOidcSubject(): string;

    /**
     * The value of one claim about this user, or null when there is none.
     */
    public function resolveOidcClaim(string $claim): mixed;
}
