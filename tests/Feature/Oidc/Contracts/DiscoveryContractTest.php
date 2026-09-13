<?php

/**
 * What every adapter publishes about this provider.
 */
test('discovery describes this issuer and the scopes it offers', function (string $adapter) {
    $metadata = oidcAdapter($adapter)->discovery()->metadata();

    expect($metadata)->toMatchArray([
        'issuer' => config('sso.issuer'),
        'jwks_uri' => config('sso.issuer').'/.well-known/jwks.json',
        'response_types_supported' => ['code'],
        'code_challenge_methods_supported' => ['S256'],
    ])->and($metadata['scopes_supported'])->toEqualCanonicalizing(['openid', 'profile', 'email', 'roles']);
})->with('oidc adapters');

test('the key set publishes one RS256 signing key without private material', function (string $adapter) {
    $keys = oidcAdapter($adapter)->signingKeys()->keySet()['keys'];

    expect($keys)->toHaveCount(1)
        ->and($keys[0])
        ->toMatchArray(['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig'])
        ->toHaveKeys(['n', 'e', 'kid'])
        ->not->toHaveKey('d');
})->with('oidc adapters');
