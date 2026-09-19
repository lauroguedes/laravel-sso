<?php

use App\Models\Application;
use App\Models\User;
use App\Oidc\AuthorizationClaims;
use App\Oidc\Contracts\ResolvesClaims;
use App\Services\ScopeRegistry;

/**
 * How a user and the granted scopes become claims.
 *
 * Tokens and UserInfo are covered end to end in AuthorizationClaimsTest and
 * AuthorizationCodeFlowTest. These pin what those cannot reach.
 */
test('every claim a scope discloses has a value for a user', function () {
    $scopes = app(ScopeRegistry::class);
    $user = User::factory()->create();

    /*
     * A claim added to a scope without a matching value on the user would be
     * advertised by discovery and never issued.
     */
    $unresolved = array_values(array_filter(
        array_diff($scopes->claimsOf($scopes->ids()), ['sub', ...AuthorizationClaims::CLAIMS]),
        fn (string $claim): bool => $user->resolveOidcClaim($claim) === null,
    ));

    expect($unresolved)->toBe([]);
});

test('claims carry the subject and only what the scopes grant', function () {
    $user = User::factory()->create();

    expect(app(ResolvesClaims::class)->claimsFor($user, ['openid', 'email'], null))
        ->toMatchArray(['sub' => (string) $user->id, 'email' => $user->email])
        ->not->toHaveKey('name');
});

test('authorization claims are omitted when the asking application is unknown', function () {
    $user = User::factory()->create();
    Application::factory()->create()->grantAccessTo($user);

    expect(app(ResolvesClaims::class)->claimsFor($user, ['openid', 'roles'], null))
        ->not->toHaveKeys(AuthorizationClaims::CLAIMS);
});
