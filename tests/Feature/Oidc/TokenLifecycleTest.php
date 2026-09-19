<?php

use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;

/**
 * Covers the machine-to-machine grant, public clients, and the introspection
 * and revocation endpoints that relying parties use to manage token state.
 */
const CALLBACK_URI = 'https://spa.example.com/auth/callback';

/**
 * Mark a client as trusted so the consent screen is skipped.
 *
 * Consent is required by default on this server; these tests exercise the
 * token endpoints rather than the consent screen, which ConsentTest covers.
 */
function trusted(Application $client): Application
{
    $client->forceFill(['skips_authorization' => true])->save();

    return $client;
}

test('a client credentials client receives an access token without a user', function () {
    $client = app(ClientRepository::class)->createClientCredentialsGrantClient('Billing Worker');

    $response = $this->postJson('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
    ]);

    $response->assertOk()->assertJsonStructure(['token_type', 'expires_in', 'access_token']);

    expect($response->json())->not->toHaveKey('id_token');
});

test('a client credentials client is rejected with the wrong secret', function () {
    $client = app(ClientRepository::class)->createClientCredentialsGrantClient('Billing Worker');

    $response = $this->postJson('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $client->id,
        'client_secret' => 'not-the-secret',
    ]);

    $response->assertUnauthorized();
});

test('a public client completes the flow with PKCE and no client secret', function () {
    $user = User::factory()->create();

    $client = trusted(app(ClientRepository::class)
        ->createAuthorizationCodeGrantClient('Mobile App', [CALLBACK_URI], confidential: false));

    $verifier = Str::random(64);

    $authorization = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => CALLBACK_URI,
        'response_type' => 'code',
        'scope' => 'openid email',
        'code_challenge' => pkceChallenge($verifier),
        'code_challenge_method' => 'S256',
    ]));

    parse_str(parse_url($authorization->headers->get('Location'), PHP_URL_QUERY), $query);

    $response = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'redirect_uri' => CALLBACK_URI,
        'code_verifier' => $verifier,
        'code' => $query['code'],
    ]);

    $response->assertOk()->assertJsonStructure(['access_token', 'id_token']);
});

test('a public client cannot exchange a code without a code verifier', function () {
    $user = User::factory()->create();

    $client = trusted(app(ClientRepository::class)
        ->createAuthorizationCodeGrantClient('Mobile App', [CALLBACK_URI], confidential: false));

    $verifier = Str::random(64);

    $authorization = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => CALLBACK_URI,
        'response_type' => 'code',
        'scope' => 'openid',
        'code_challenge' => pkceChallenge($verifier),
        'code_challenge_method' => 'S256',
    ]));

    parse_str(parse_url($authorization->headers->get('Location'), PHP_URL_QUERY), $query);

    $response = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'redirect_uri' => CALLBACK_URI,
        'code' => $query['code'],
    ]);

    $response->assertBadRequest();
});

test('a public client must use PKCE to start an authorization request', function () {
    $user = User::factory()->create();

    $client = trusted(app(ClientRepository::class)
        ->createAuthorizationCodeGrantClient('Mobile App', [CALLBACK_URI], confidential: false));

    $response = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => CALLBACK_URI,
        'response_type' => 'code',
        'scope' => 'openid',
    ]));

    $response->assertBadRequest()->assertJson(['error' => 'invalid_request']);

    expect($response->json('hint'))->toContain('Code challenge');
});

describe('introspection and revocation', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        $this->client = trusted(app(ClientRepository::class)
            ->createAuthorizationCodeGrantClient('Reporting', [CALLBACK_URI]));

        $this->secret = $this->client->plainSecret;

        $verifier = Str::random(64);

        $authorization = $this->actingAs($this->user)->get('/oauth/authorize?'.http_build_query([
            'client_id' => $this->client->id,
            'redirect_uri' => CALLBACK_URI,
            'response_type' => 'code',
            'scope' => 'openid email',
            'code_challenge' => pkceChallenge($verifier),
            'code_challenge_method' => 'S256',
        ]));

        parse_str(parse_url($authorization->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->tokens = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->client->id,
            'client_secret' => $this->secret,
            'redirect_uri' => CALLBACK_URI,
            'code_verifier' => $verifier,
            'code' => $query['code'],
        ])->json();
    });

    test('introspection reports a freshly issued token as active, with no fields beyond these', function () {
        $introspection = $this->postJson('/oauth/introspect', [
            'client_id' => $this->client->id,
            'client_secret' => $this->secret,
            'token' => $this->tokens['access_token'],
        ])->assertOk()->json();

        expect(Arr::except($introspection, ['exp', 'iat']))->toEqual([
            'active' => true,
            'scope' => 'openid email',
            'client_id' => $this->client->id,
            'username' => $this->user->email,
            'token_type' => 'Bearer',
            'sub' => (string) $this->user->id,
            'aud' => $this->client->id,
            'iss' => config('sso.issuer'),
        ])->and($introspection['exp'])->toBeInt()->toBeGreaterThan($introspection['iat']);
    });

    test('introspection requires client authentication', function () {
        $response = $this->postJson('/oauth/introspect', [
            'client_id' => $this->client->id,
            'client_secret' => 'not-the-secret',
            'token' => $this->tokens['access_token'],
        ]);

        $response->assertUnauthorized();
    });

    test('introspection reports an unknown token as inactive', function () {
        $response = $this->postJson('/oauth/introspect', [
            'client_id' => $this->client->id,
            'client_secret' => $this->secret,
            'token' => 'not-a-real-token',
        ]);

        $response->assertOk()->assertExactJson(['active' => false]);
    });

    test('a revoked access token is reported as inactive', function () {
        $this->postJson('/oauth/revoke', [
            'client_id' => $this->client->id,
            'client_secret' => $this->secret,
            'token' => $this->tokens['access_token'],
        ])->assertOk();

        $response = $this->postJson('/oauth/introspect', [
            'client_id' => $this->client->id,
            'client_secret' => $this->secret,
            'token' => $this->tokens['access_token'],
        ]);

        expect($response->json('active'))->toBeFalse();
    });

    test('a revoked access token no longer reaches userinfo', function () {
        $this->postJson('/oauth/revoke', [
            'client_id' => $this->client->id,
            'client_secret' => $this->secret,
            'token' => $this->tokens['access_token'],
        ])->assertOk();

        $this->getJson('/oauth/userinfo', [
            'Authorization' => 'Bearer '.$this->tokens['access_token'],
        ])->assertUnauthorized();
    });

    test('revoking an access token also revokes its refresh token', function () {
        $this->postJson('/oauth/revoke', [
            'client_id' => $this->client->id,
            'client_secret' => $this->secret,
            'token' => $this->tokens['access_token'],
        ])->assertOk();

        $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->client->id,
            'client_secret' => $this->secret,
            'refresh_token' => $this->tokens['refresh_token'],
        ])->assertBadRequest()->assertJson(['error' => 'invalid_grant']);
    });

    test('revoking a refresh token also revokes its access token', function () {
        $this->postJson('/oauth/revoke', [
            'client_id' => $this->client->id,
            'client_secret' => $this->secret,
            'token' => $this->tokens['refresh_token'],
            'token_type_hint' => 'refresh_token',
        ])->assertOk();

        $this->getJson('/oauth/userinfo', [
            'Authorization' => 'Bearer '.$this->tokens['access_token'],
        ])->assertUnauthorized();
    });

    test('a client cannot revoke a token issued to another client', function () {
        $other = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Other', [CALLBACK_URI]);

        $this->postJson('/oauth/revoke', [
            'client_id' => $other->id,
            'client_secret' => $other->plainSecret,
            'token' => $this->tokens['access_token'],
        ])->assertOk();

        $this->getJson('/oauth/userinfo', [
            'Authorization' => 'Bearer '.$this->tokens['access_token'],
        ])->assertOk();
    });

    test('userinfo also answers a POST request', function () {
        $this->postJson('/oauth/userinfo', [], [
            'Authorization' => 'Bearer '.$this->tokens['access_token'],
        ])->assertOk()->assertJson([
            'sub' => (string) $this->user->id,
            'email' => $this->user->email,
        ]);
    });

    test('revocation requires client authentication', function () {
        $response = $this->postJson('/oauth/revoke', [
            'client_id' => $this->client->id,
            'client_secret' => 'not-the-secret',
            'token' => $this->tokens['access_token'],
        ]);

        $response->assertUnauthorized();
    });
});
