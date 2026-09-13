<?php

use App\Models\User;

test('discovery advertises the endpoints of this issuer', function () {
    $response = $this->getJson('/.well-known/openid-configuration');

    $response->assertOk()->assertJson([
        'issuer' => config('sso.issuer'),
        'authorization_endpoint' => config('sso.issuer').'/oauth/authorize',
        'token_endpoint' => config('sso.issuer').'/oauth/token',
        'userinfo_endpoint' => config('sso.issuer').'/oauth/userinfo',
        'jwks_uri' => config('sso.issuer').'/.well-known/jwks.json',
        'introspection_endpoint' => config('sso.issuer').'/oauth/introspect',
        'revocation_endpoint' => config('sso.issuer').'/oauth/revoke',
        'end_session_endpoint' => config('sso.issuer').'/oauth/logout',
    ]);
});

test('discovery advertises the scopes this server offers', function () {
    $response = $this->getJson('/.well-known/openid-configuration');

    /*
     * The three OpenID Connect scopes, plus "roles", which carries the
     * authorization a user holds in the requesting application.
     */
    expect($response->json('scopes_supported'))
        ->toEqualCanonicalizing(['openid', 'profile', 'email', 'roles']);
});

test('discovery advertises the authorization claims', function () {
    $response = $this->getJson('/.well-known/openid-configuration');

    expect($response->json('claims_supported'))
        ->toContain('roles')
        ->toContain('permissions');
});

test('discovery does not advertise the implicit grant', function () {
    $response = $this->getJson('/.well-known/openid-configuration');

    expect($response->json('response_types_supported'))->toBe(['code'])
        ->and($response->json('grant_types_supported'))->not->toContain('implicit');
});

test('discovery does not advertise the password grant', function () {
    $response = $this->getJson('/.well-known/openid-configuration');

    expect($response->json('grant_types_supported'))
        ->toEqualCanonicalizing(['authorization_code', 'refresh_token', 'client_credentials']);
});

test('discovery requires the S256 PKCE challenge method', function () {
    $response = $this->getJson('/.well-known/openid-configuration');

    expect($response->json('code_challenge_methods_supported'))->toBe(['S256']);
});

test('discovery allows public clients to authenticate without a secret', function () {
    $response = $this->getJson('/.well-known/openid-configuration');

    expect($response->json('token_endpoint_auth_methods_supported'))->toContain('none');
});

test('jwks exposes a single RS256 signing key without private material', function () {
    $response = $this->getJson('/.well-known/jwks.json');

    $response->assertOk();

    $key = $response->json('keys.0');

    expect($key)->toMatchArray(['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig'])
        ->and($key)->toHaveKeys(['n', 'e', 'kid'])
        ->and($key)->not->toHaveKey('d');
});

test('discovery is reachable without authentication', function () {
    $this->assertGuest();

    $this->getJson('/.well-known/openid-configuration')->assertOk();
    $this->getJson('/.well-known/jwks.json')->assertOk();
});

test('userinfo returns 401 without an access token', function () {
    $this->getJson('/oauth/userinfo')->assertUnauthorized();
});

test('userinfo returns 401 for a session authenticated user without a token', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/oauth/userinfo')
        ->assertUnauthorized();
});

test('discovery advertises the signing algorithm, subject type and revocation client authentication', function () {
    $response = $this->getJson('/.well-known/openid-configuration');

    expect($response->json())
        ->id_token_signing_alg_values_supported->toBe(['RS256'])
        ->subject_types_supported->toBe(['public'])
        ->revocation_endpoint_auth_methods_supported->toEqualCanonicalizing(['client_secret_basic', 'client_secret_post', 'none']);
});

test('clients may cache the discovery document for an hour and the key set for a day', function () {
    $discovery = $this->get('/.well-known/openid-configuration')->headers;
    $keySet = $this->get('/.well-known/jwks.json')->headers;

    expect($discovery->hasCacheControlDirective('public'))->toBeTrue()
        ->and($discovery->getCacheControlDirective('max-age'))->toBe('3600')
        ->and($keySet->hasCacheControlDirective('public'))->toBeTrue()
        ->and($keySet->getCacheControlDirective('max-age'))->toBe('86400');
});

test('discovery offers introspection to confidential clients only', function () {
    expect($this->getJson('/.well-known/openid-configuration')->json('introspection_endpoint_auth_methods_supported'))
        ->toEqualCanonicalizing(['client_secret_basic', 'client_secret_post']);
});
