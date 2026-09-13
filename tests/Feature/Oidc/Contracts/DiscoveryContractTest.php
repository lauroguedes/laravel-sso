<?php

/**
 * What every adapter publishes about this provider, and the keys behind it.
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

test('the key set publishes RS256 signing keys without private material', function (string $adapter) {
    $keys = oidcAdapter($adapter)->signingKeys()->keySet()['keys'];

    expect($keys)->not->toBeEmpty()
        ->each->toMatchArray(['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig'])
        ->toHaveKeys(['n', 'e', 'kid'])
        ->not->toHaveKey('d');
})->with('oidc adapters');

test('the active key is published, and its private half signs what its public half verifies', function (string $adapter) {
    $keys = oidcAdapter($adapter)->signingKeys();

    openssl_sign('payload', $signature, $keys->privateKey(), OPENSSL_ALGO_SHA256);

    expect(array_column($keys->keySet()['keys'], 'kid'))->toContain($keys->keyId())
        ->and(openssl_verify('payload', $signature, $keys->publicKey(), OPENSSL_ALGO_SHA256))->toBe(1);
})->with('oidc adapters');

/*
 * The native document replaces the package's without a relying party
 * noticing. Phase by phase this narrows to the values the native adapter
 * deliberately changes.
 */
test('the native discovery document is the package document, key for key', function () {
    expect(oidcAdapter('native')->discovery()->metadata())
        ->toBe(oidcAdapter('admin9')->discovery()->metadata());
});
