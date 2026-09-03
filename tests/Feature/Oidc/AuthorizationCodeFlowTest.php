<?php

use App\Models\User;
use Illuminate\Support\Once;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;

/**
 * Exercises the full Authorization Code + PKCE flow end to end, which is the
 * default mechanism for interactive applications on this server.
 */
const REDIRECT_URI = 'https://client.example.com/auth/callback';

function pkcePair(): array
{
    $verifier = Str::random(64);

    $challenge = strtr(rtrim(base64_encode(hash('sha256', $verifier, true)), '='), '+/', '-_');

    return [$verifier, $challenge];
}

/**
 * Drive the authorization endpoint and return the issued authorization code.
 *
 * Clients without an owner are first party, so the consent screen is skipped
 * and the endpoint redirects straight back to the client.
 */
function authorizationCodeFor(
    $test,
    User $user,
    string $clientId,
    string $challenge,
    string $scope = 'openid profile email',
    string $redirectUri = REDIRECT_URI,
): string {
    $response = $test->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => $scope,
        'state' => 'opaque-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    $response->assertRedirectContains($redirectUri);

    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query)->toHaveKey('code')
        ->and($query['state'])->toBe('opaque-state');

    return $query['code'];
}

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
    ]);

    $this->client = app(ClientRepository::class)
        ->createAuthorizationCodeGrantClient('Customer Portal', [REDIRECT_URI]);

    $this->plainSecret = $this->client->plainSecret;
});

test('a confidential client exchanges an authorization code for tokens', function () {
    [$verifier, $challenge] = pkcePair();

    $code = authorizationCodeFor($this, $this->user, $this->client->id, $challenge);

    $response = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->client->id,
        'client_secret' => $this->plainSecret,
        'redirect_uri' => REDIRECT_URI,
        'code_verifier' => $verifier,
        'code' => $code,
    ]);

    $response->assertOk()->assertJsonStructure([
        'token_type', 'expires_in', 'access_token', 'refresh_token', 'id_token',
    ]);

    expect($response->json('token_type'))->toBe('Bearer')
        ->and($response->json('expires_in'))->toBe(config('sso.oauth.access_token_ttl'));
});

test('the id token carries the standard claims for the granted scopes', function () {
    [$verifier, $challenge] = pkcePair();

    $code = authorizationCodeFor($this, $this->user, $this->client->id, $challenge);

    $response = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->client->id,
        'client_secret' => $this->plainSecret,
        'redirect_uri' => REDIRECT_URI,
        'code_verifier' => $verifier,
        'code' => $code,
    ]);

    $claims = (new Parser(new JoseEncoder))
        ->parse($response->json('id_token'))
        ->claims();

    expect($claims->get('iss'))->toBe(config('sso.issuer'))
        ->and($claims->get('sub'))->toBe((string) $this->user->id)
        ->and($claims->get('aud'))->toBe([$this->client->id])
        ->and($claims->get('name'))->toBe('Alice Smith')
        ->and($claims->get('email'))->toBe('alice@example.com')
        ->and($claims->get('email_verified'))->toBeTrue();
});

test('the id token is signed with RS256', function () {
    [$verifier, $challenge] = pkcePair();

    $code = authorizationCodeFor($this, $this->user, $this->client->id, $challenge);

    $idToken = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->client->id,
        'client_secret' => $this->plainSecret,
        'redirect_uri' => REDIRECT_URI,
        'code_verifier' => $verifier,
        'code' => $code,
    ])->json('id_token');

    $headers = (new Parser(new JoseEncoder))->parse($idToken)->headers();

    expect($headers->get('alg'))->toBe('RS256')
        ->and($headers->get('typ'))->toBe('JWT');
});

test('userinfo returns the claims of the token owner', function () {
    [$verifier, $challenge] = pkcePair();

    $code = authorizationCodeFor($this, $this->user, $this->client->id, $challenge);

    $accessToken = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->client->id,
        'client_secret' => $this->plainSecret,
        'redirect_uri' => REDIRECT_URI,
        'code_verifier' => $verifier,
        'code' => $code,
    ])->json('access_token');

    $response = $this->getJson('/oauth/userinfo', [
        'Authorization' => 'Bearer '.$accessToken,
    ]);

    $response->assertOk()->assertJson([
        'sub' => (string) $this->user->id,
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
        'email_verified' => true,
    ]);
});

test('userinfo omits email claims when the email scope was not granted', function () {
    [$verifier, $challenge] = pkcePair();

    $code = authorizationCodeFor($this, $this->user, $this->client->id, $challenge, scope: 'openid profile');

    $accessToken = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->client->id,
        'client_secret' => $this->plainSecret,
        'redirect_uri' => REDIRECT_URI,
        'code_verifier' => $verifier,
        'code' => $code,
    ])->json('access_token');

    $response = $this->getJson('/oauth/userinfo', [
        'Authorization' => 'Bearer '.$accessToken,
    ]);

    $response->assertOk()->assertJsonMissing(['email' => 'alice@example.com']);

    expect($response->json())->not->toHaveKey('email_verified');
});

test('a refresh token exchanges for a new access token', function () {
    [$verifier, $challenge] = pkcePair();

    $code = authorizationCodeFor($this, $this->user, $this->client->id, $challenge);

    $refreshToken = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->client->id,
        'client_secret' => $this->plainSecret,
        'redirect_uri' => REDIRECT_URI,
        'code_verifier' => $verifier,
        'code' => $code,
    ])->json('refresh_token');

    $response = $this->postJson('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $this->client->id,
        'client_secret' => $this->plainSecret,
        'refresh_token' => $refreshToken,
    ]);

    $response->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);
});

test('an authorization code cannot be redeemed twice', function () {
    [$verifier, $challenge] = pkcePair();

    $code = authorizationCodeFor($this, $this->user, $this->client->id, $challenge);

    $payload = [
        'grant_type' => 'authorization_code',
        'client_id' => $this->client->id,
        'client_secret' => $this->plainSecret,
        'redirect_uri' => REDIRECT_URI,
        'code_verifier' => $verifier,
        'code' => $code,
    ];

    $this->postJson('/oauth/token', $payload)->assertOk();

    $this->postJson('/oauth/token', $payload)->assertBadRequest();
});

test('rejects a code verifier that does not match the challenge', function () {
    [, $challenge] = pkcePair();

    $code = authorizationCodeFor($this, $this->user, $this->client->id, $challenge);

    $response = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->client->id,
        'client_secret' => $this->plainSecret,
        'redirect_uri' => REDIRECT_URI,
        'code_verifier' => Str::random(64),
        'code' => $code,
    ]);

    $response->assertBadRequest();
});

test('rejects an authorization request for an unregistered redirect uri', function () {
    [, $challenge] = pkcePair();

    $response = $this->actingAs($this->user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $this->client->id,
        'redirect_uri' => 'https://attacker.example.com/auth/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    $response->assertUnauthorized()->assertJson(['error' => 'invalid_client']);

    expect($response->headers->get('Location'))->toBeNull();
});

test('rejects an authorization request from a guest', function () {
    [, $challenge] = pkcePair();

    $response = $this->get('/oauth/authorize?'.http_build_query([
        'client_id' => $this->client->id,
        'redirect_uri' => REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'openid',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    $response->assertRedirect(route('login'));
});

test('rejects a token request with the wrong client secret', function () {
    [$verifier, $challenge] = pkcePair();

    $code = authorizationCodeFor($this, $this->user, $this->client->id, $challenge);

    $response = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->client->id,
        'client_secret' => 'not-the-secret',
        'redirect_uri' => REDIRECT_URI,
        'code_verifier' => $verifier,
        'code' => $code,
    ]);

    $response->assertUnauthorized();
});

test('a revoked client cannot start an authorization request', function () {
    [, $challenge] = pkcePair();

    $this->client->forceFill(['revoked' => true])->save();

    $response = $this->actingAs($this->user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $this->client->id,
        'redirect_uri' => REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'openid',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    $response->assertUnauthorized()->assertJson(['error' => 'invalid_client']);
});

test('a revoked client cannot redeem an authorization code it already holds', function () {
    [$verifier, $challenge] = pkcePair();

    $code = authorizationCodeFor($this, $this->user, $this->client->id, $challenge);

    $this->client->forceFill(['revoked' => true])->save();

    /*
     * Passport memoizes client lookups with once(), which is scoped to the
     * process rather than the request. Each real request gets a fresh process,
     * so the memo is flushed here to reproduce production behaviour.
     */
    Once::flush();

    $response = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->client->id,
        'client_secret' => $this->plainSecret,
        'redirect_uri' => REDIRECT_URI,
        'code_verifier' => $verifier,
        'code' => $code,
    ]);

    $response->assertUnauthorized()->assertJson(['error' => 'invalid_client']);
});
