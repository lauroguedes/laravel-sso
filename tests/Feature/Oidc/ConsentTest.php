<?php

use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;

/**
 * Consent is an explicit decision on this server.
 *
 * Passport treats every client without an owner as first party and skips the
 * approval screen for it, which would silently apply to every application an
 * administrator registers. App\Models\Application overrides that so approval
 * is shown unless the application has been marked trusted.
 */
const CONSENT_REDIRECT_URI = 'https://consent.example.com/auth/callback';

function authorizationRequest($test, User $user, Application $client)
{
    $verifier = Str::random(64);

    return $test->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => CONSENT_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'openid email',
        'code_challenge' => strtr(rtrim(base64_encode(hash('sha256', $verifier, true)), '='), '+/', '-_'),
        'code_challenge_method' => 'S256',
    ]));
}

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->client = app(ClientRepository::class)
        ->createAuthorizationCodeGrantClient('Customer Portal', [CONSENT_REDIRECT_URI]);
});

test('a newly registered application asks the user to approve it', function () {
    expect($this->client->skips_authorization)->toBeFalse();

    $response = authorizationRequest($this, $this->user, $this->client);

    $response->assertOk();

    expect($response->headers->get('Location'))->toBeNull();
});

test('an application marked trusted redirects without asking for approval', function () {
    $this->client->forceFill(['skips_authorization' => true])->save();

    $response = authorizationRequest($this, $this->user, $this->client);

    $response->assertRedirectContains(CONSENT_REDIRECT_URI);

    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query)->toHaveKey('code');
});

test('an administrator marks an application trusted', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->put(route('applications.update', $this->client), [
        'name' => $this->client->name,
        'redirect_uris' => [CONSENT_REDIRECT_URI],
        'scopes' => ['openid', 'email'],
        'skips_authorization' => true,
    ]);

    expect($this->client->refresh()->skips_authorization)->toBeTrue();
});
