<?php

use App\Oidc\Contracts\DiscoversProvider;
use App\Oidc\Contracts\ProvidesSigningKeys;

/**
 * What this provider publishes about itself, and the keys behind it.
 */
test('discovery describes this issuer and the scopes it offers', function () {
    $discovery = app(DiscoversProvider::class);
    $metadata = $discovery->metadata();

    expect($metadata)->toMatchArray([
        'issuer' => config('sso.issuer'),
        'jwks_uri' => config('sso.issuer').'/.well-known/jwks.json',
        'response_types_supported' => ['code'],
        'code_challenge_methods_supported' => ['S256'],
    ])->and($metadata['scopes_supported'])->toEqualCanonicalizing(['openid', 'profile', 'email', 'roles'])
        ->and($discovery->issuer())->toBe($metadata['issuer']);
});

test('the key set publishes RS256 signing keys without private material', function () {
    expect(app(ProvidesSigningKeys::class)->keySet()['keys'])->not->toBeEmpty()
        ->each->toMatchArray(['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig'])
        ->toHaveKeys(['n', 'e', 'kid'])
        ->not->toHaveKey('d');
});

test('the active key is published, and its private half signs what its public half verifies', function () {
    $keys = app(ProvidesSigningKeys::class);

    openssl_sign('payload', $signature, $keys->privateKey(), OPENSSL_ALGO_SHA256);

    expect(array_column($keys->keySet()['keys'], 'kid'))->toContain($keys->keyId())
        ->and(openssl_verify('payload', $signature, $keys->publicKey(), OPENSSL_ALGO_SHA256))->toBe(1);
});
