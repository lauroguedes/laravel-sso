<?php

use App\Models\Application;
use App\Models\User;

/**
 * The OAuth2 server requires PKCE of public clients on its own. This covers
 * the part it leaves optional: confidential clients, which current security
 * guidance says should use it too.
 */
const PKCE_REDIRECT_URI = 'https://pkce.example.com/auth/callback';

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->application = Application::factory()
        ->trusted()
        ->withSecret('pkce-secret')
        ->create(['redirect_uris' => [PKCE_REDIRECT_URI]]);
});

test('a confidential client must use PKCE', function () {
    $response = authorizationRequest($this->user, $this->application, ['code_challenge' => null, 'code_challenge_method' => null]);

    $response->assertBadRequest()->assertJson(['error' => 'invalid_request']);

    expect($response->json('hint'))->toContain('Code challenge');
});

test('a confidential client using PKCE is authorized', function () {
    $response = authorizationRequest($this->user, $this->application);

    $response->assertRedirectContains(PKCE_REDIRECT_URI);
});

test('a confidential client may skip PKCE when the requirement is turned off', function () {
    config()->set('sso.oauth.require_pkce', false);

    $response = authorizationRequest($this->user, $this->application, ['code_challenge' => null, 'code_challenge_method' => null]);

    $response->assertRedirectContains(PKCE_REDIRECT_URI);
});

test('a public client must use PKCE even when the requirement is turned off', function () {
    /*
     * The OAuth2 server enforces this itself, so the deployment switch cannot
     * weaken it — only extend it to confidential clients.
     */
    config()->set('sso.oauth.require_pkce', false);

    $public = Application::factory()->trusted()->asPublic()->create([
        'redirect_uris' => [PKCE_REDIRECT_URI],
    ]);

    $response = authorizationRequest($this->user, $public, ['code_challenge' => null, 'code_challenge_method' => null]);

    $response->assertBadRequest()->assertJson(['error' => 'invalid_request']);
});

test('a code issued with a challenge cannot be redeemed without the verifier', function () {
    [$verifier, $challenge] = pkcePair();

    $authorization = authorizationRequest($this->user, $this->application, [
        'code_challenge' => $challenge,
    ]);

    parse_str(parse_url($authorization->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->application->id,
        'client_secret' => 'pkce-secret',
        'redirect_uri' => PKCE_REDIRECT_URI,
        'code' => $query['code'],
    ])->assertBadRequest();
});

test('the discovery document is reachable while PKCE is required', function () {
    /*
     * The requirement belongs to the authorization route alone, so this guards
     * against it reaching the well-known endpoints.
     */
    $this->getJson('/.well-known/openid-configuration')->assertOk();
    $this->getJson('/.well-known/jwks.json')->assertOk();
});
