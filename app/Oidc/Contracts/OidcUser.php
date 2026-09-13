<?php

declare(strict_types=1);

namespace App\Oidc\Contracts;

/**
 * Somebody who signs in to applications through this provider.
 *
 * Which claims they disclose is decided by ResolvesClaims, not by the user.
 */
interface OidcUser
{
    /**
     * The stable identifier relying parties know this user by.
     */
    public function getOidcSubject(): string;
}
