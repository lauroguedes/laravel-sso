<?php

use App\Models\Application;
use App\Models\User;

/**
 * The consent screen is what an untrusted application's users actually see.
 * ConsentTest covers when it is shown; this covers what it does.
 */
const CONSENT_SCREEN_REDIRECT_URI = 'https://consent-screen.example.com/auth/callback';

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Alice Smith']);

    $this->application = Application::factory()
        ->withSecret('consent-secret')
        ->create([
            'name' => 'Reporting',
            'description' => 'Company reporting',
            'redirect_uris' => [CONSENT_SCREEN_REDIRECT_URI],
        ]);
});

test('the consent screen names the application and what it is asking for', function () {
    $response = authorizationRequest($this->user, $this->application, ['scope' => 'openid email', 'state' => 'opaque-state']);

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('oauth/Authorize')
        ->where('application.name', 'Reporting')
        ->where('application.description', 'Company reporting')
        ->has('scopes', 2)
        ->has('authToken'));
});

test('the consent screen describes each scope rather than naming it', function () {
    $response = authorizationRequest($this->user, $this->application, ['scope' => 'openid email']);

    $response->assertInertia(fn ($page) => $page->where(
        'scopes',
        fn ($scopes) => collect($scopes)->pluck('description')->every(
            fn (string $description): bool => $description !== '' && ! str_contains($description, 'openid'),
        ),
    ));
});

test('approving issues an authorization code', function () {
    [$verifier, $challenge] = pkcePair();

    $response = authorizationRequest($this->user, $this->application, [
        'scope' => 'openid email',
        'state' => 'opaque-state',
        'code_challenge' => $challenge,
    ]);

    $authToken = $response->viewData('page')['props']['authToken'];

    $approval = $this->actingAs($this->user)->post('/oauth/authorize', [
        'auth_token' => $authToken,
        'state' => 'opaque-state',
    ]);

    $approval->assertRedirectContains(CONSENT_SCREEN_REDIRECT_URI);

    parse_str(parse_url($approval->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query)->toHaveKey('code');

    $tokens = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->application->id,
        'client_secret' => 'consent-secret',
        'redirect_uri' => CONSENT_SCREEN_REDIRECT_URI,
        'code_verifier' => $verifier,
        'code' => $query['code'],
    ]);

    $tokens->assertOk()->assertJsonStructure(['access_token', 'id_token']);
});

test('denying sends the application an access_denied error instead of a code', function () {
    $response = authorizationRequest($this->user, $this->application, ['scope' => 'openid email', 'state' => 'opaque-state']);

    $authToken = $response->viewData('page')['props']['authToken'];

    $denial = $this->actingAs($this->user)->delete('/oauth/authorize', [
        'auth_token' => $authToken,
        'state' => 'opaque-state',
    ]);

    $denial->assertRedirectContains(CONSENT_SCREEN_REDIRECT_URI);

    parse_str(parse_url($denial->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query)->not->toHaveKey('code')
        ->and($query['error'])->toBe('access_denied');
});

test('an approval cannot be replayed with a stale token', function () {
    $response = authorizationRequest($this->user, $this->application, ['scope' => 'openid email', 'state' => 'opaque-state']);

    $authToken = $response->viewData('page')['props']['authToken'];

    $this->actingAs($this->user)
        ->post('/oauth/authorize', ['auth_token' => $authToken, 'state' => 'opaque-state'])
        ->assertRedirectContains(CONSENT_SCREEN_REDIRECT_URI);

    /*
     * The token is pulled from the session when it is used, so a second
     * approval has nothing to match against.
     */
    $this->actingAs($this->user)
        ->post('/oauth/authorize', ['auth_token' => $authToken, 'state' => 'opaque-state'])
        ->assertForbidden();
});

test('an approval carrying someone else\'s token is refused', function () {
    authorizationRequest($this->user, $this->application, ['scope' => 'openid email']);

    $this->actingAs($this->user)
        ->post('/oauth/authorize', ['auth_token' => 'not-the-token', 'state' => 'opaque-state'])
        ->assertForbidden();
});

test('a guest is sent to sign in before being asked to consent', function () {
    authorizationRequest(null, $this->application)->assertRedirect(route('login'));
});
