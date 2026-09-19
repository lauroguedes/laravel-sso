<?php

declare(strict_types=1);

namespace App\Oidc;

use App\Oidc\Contracts\OidcUser;
use App\Oidc\Contracts\ResolvesClaims;
use App\Services\ScopeRegistry;

/**
 * The claims a user discloses, decided by the scopes an application was granted.
 *
 * The scopes decide which claims, from the list discovery advertises, and the
 * user supplies each value. Roles and permissions are only disclosed to a
 * known client, and omitted rather than guessed without one.
 */
class Claims implements ResolvesClaims
{
    /**
     * The claims every ID Token may carry, whatever the scopes.
     */
    private const PROTOCOL_CLAIMS = ['sub', 'iss', 'aud', 'exp', 'iat', 'auth_time'];

    public function __construct(
        private readonly ScopeRegistry $scopes,
        private readonly AuthorizationClaims $authorization,
    ) {}

    public function claimsFor(OidcUser $user, array $scopes, ?string $clientId): array
    {
        $granted = $this->scopes->claimsOf($scopes);
        $claims = ['sub' => $user->getOidcSubject()];

        foreach (array_diff($granted, ['sub', ...AuthorizationClaims::CLAIMS]) as $claim) {
            $value = $user->resolveOidcClaim($claim);

            if ($value !== null) {
                $claims[$claim] = $value;
            }
        }

        $requested = array_intersect(AuthorizationClaims::CLAIMS, $granted);

        if ($requested !== [] && $clientId !== null) {
            $claims += array_intersect_key($this->authorization->for($claims['sub'], $clientId), array_flip($requested));
        }

        return $claims;
    }

    public function supportedClaims(): array
    {
        return array_values(array_unique([...self::PROTOCOL_CLAIMS, ...$this->scopes->claimsOf($this->scopes->ids())]));
    }
}
