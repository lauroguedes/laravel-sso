<?php

declare(strict_types=1);

namespace App\Oidc\Passport;

use App\Models\User;
use App\Oidc\Contracts\IdTokenRequest;
use App\Oidc\Contracts\IssuesIdTokens;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;

/**
 * Passport's token response, with an ID Token when openid was granted.
 *
 * The nonce and sign-in time come from the access token just stored, which
 * recorded them. Passport keeps this instance for the life of the application,
 * so the issuer is resolved when a response is built.
 */
class IdTokenResponse extends BearerTokenResponse
{
    protected function getExtraParams(AccessTokenEntityInterface $accessToken): array
    {
        $scopes = array_map(fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(), $accessToken->getScopes());
        $userId = $accessToken->getUserIdentifier();

        if ($userId === null || ! in_array('openid', $scopes, true)) {
            return [];
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            return [];
        }

        $recorded = Passport::token()->newQuery()->find($accessToken->getIdentifier(), ['nonce', 'auth_time']);
        $authTime = $recorded?->getAttribute('auth_time');

        return ['id_token' => app(IssuesIdTokens::class)->issue(new IdTokenRequest(
            user: $user,
            clientId: $accessToken->getClient()->getIdentifier(),
            scopes: $scopes,
            nonce: $recorded?->getAttribute('nonce'),
            authTime: $authTime === null ? null : (int) $authTime,
        ))];
    }
}
