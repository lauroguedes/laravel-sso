<?php

use App\Models\Application;
use App\Models\User;
use App\Oidc\Exceptions\SigningKeyUnavailable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia as Assert;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;

/**
 * Rotating the signing key without stranding the tokens signed before it.
 *
 * OpenID Connect Core 1.0 section 10.1.1: "The JWK Set document at the jwks_uri
 * SHOULD retain recently decommissioned signing keys for a reasonable period of
 * time to facilitate a smooth transition."
 */

/**
 * Keep a retired public key published, supplied the way an environment would.
 */
function retainPublicKey(string $publicKey): void
{
    config(['sso.previous_public_key' => str_replace("\n", '\n', $publicKey)]);
}

/**
 * What restarting the server does after a rotation.
 *
 * Passport reads its keys once, when it builds these servers, and each route
 * keeps the controller it resolved them into. A real restart starts over, so
 * the test has to forget all three.
 */
function restartAfterRotation(): void
{
    app()->forgetInstance(AuthorizationServer::class);
    app()->forgetInstance(ResourceServer::class);
    app('auth')->forgetGuards();

    foreach (app('router')->getRoutes() as $route) {
        $route->flushController();
    }
}

test('only the active key is published until a retired one is configured', function () {
    withoutKeyFiles(function () {
        $publicKey = signWithFreshPair();

        $this->getJson('/.well-known/jwks.json')
            ->assertJsonCount(1, 'keys')
            ->assertJsonPath('keys.0.kid', keyIdOf($publicKey));
    });
});

test('a token signed before the rotation still verifies against the published key set', function () {
    withoutKeyFiles(function () {
        $oldPublic = signWithFreshPair();

        $idToken = (new Parser(new JoseEncoder))->parse(
            idTokenHint(User::factory()->create(), Application::factory()->create()),
        );

        $newPublic = signWithFreshPair();
        retainPublicKey($oldPublic);

        $keys = collect($this->getJson('/.well-known/jwks.json')->json('keys'));

        /*
         * A relying party picks the key by the token's "kid" and goes back to
         * the key set when it does not recognise one. The key it finds must be
         * the one that signed the token.
         */
        $published = $keys->firstWhere('kid', $idToken->headers()->get('kid'));
        $modulus = openssl_pkey_get_details(openssl_pkey_get_public($oldPublic))['rsa']['n'];

        expect($keys->pluck('kid')->all())->toBe([keyIdOf($newPublic), keyIdOf($oldPublic)])
            ->and($published['n'])->toBe((new JoseEncoder)->base64UrlEncode($modulus))
            ->and((new Sha256)->verify($idToken->signature()->hash(), $idToken->payload(), InMemory::plainText($oldPublic)))->toBeTrue();
    });
});

test('the retired key can be left beside the active pair as a file', function () {
    withoutKeyFiles(function (string $directory) {
        $oldPublic = signWithFreshPair();
        $publicKey = signWithFreshPair();

        file_put_contents($directory.'/oauth-previous-public.key', $oldPublic);

        expect($this->getJson('/.well-known/jwks.json')->json('keys.*.kid'))
            ->toBe([keyIdOf($publicKey), keyIdOf($oldPublic)]);
    });
});

test('a retired key identical to the active one is published once', function () {
    withoutKeyFiles(function () {
        retainPublicKey(signWithFreshPair());

        $this->getJson('/.well-known/jwks.json')->assertJsonCount(1, 'keys');
    });
});

test('a malformed retired key is left out and reported, and the key set stays up', function () {
    Exceptions::fake();

    withoutKeyFiles(function () {
        $publicKey = signWithFreshPair();
        retainPublicKey('not a key');

        $this->getJson('/.well-known/jwks.json')
            ->assertOk()
            ->assertJsonCount(1, 'keys')
            ->assertJsonPath('keys.0.kid', keyIdOf($publicKey));
    });

    Exceptions::assertReported(SigningKeyUnavailable::class);
});

test('a signing key fault is reported once an hour, not on every request that meets it', function () {
    $handler = app(ExceptionHandler::class);
    $fault = new SigningKeyUnavailable('Invalid retired public key', 'Unable to parse the retired public key, so it is not published.');

    expect($handler->shouldReport($fault))->toBeTrue()
        ->and($handler->shouldReport($fault))->toBeFalse();

    $this->travel(61)->minutes();

    expect($handler->shouldReport($fault))->toBeTrue();
});

test('an access token signed before the rotation is refused here, and a refresh recovers without a sign-in', function () {
    withoutKeyFiles(function () {
        $application = Application::factory()->trusted()->withSecret('rotation-secret')->create();

        $oldPublic = signWithFreshPair();
        $tokens = issueTokens(User::factory()->create(), $application);

        signWithFreshPair();
        retainPublicKey($oldPublic);
        restartAfterRotation();

        /*
         * Passport verifies access tokens with the active key alone, so the
         * retained key does not help here. The refresh token is encrypted with
         * APP_KEY rather than signed, so it survives the rotation and comes
         * back with an access token signed by the new key.
         */
        $this->getJson('/oauth/userinfo', ['Authorization' => 'Bearer '.$tokens['access_token']])->assertUnauthorized();

        $refreshed = $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $application->id,
            'client_secret' => 'rotation-secret',
            'refresh_token' => $tokens['refresh_token'],
        ])->assertOk()->json();

        $this->getJson('/oauth/userinfo', ['Authorization' => 'Bearer '.$refreshed['access_token']])->assertOk();
    });
});

describe('an id_token_hint signed before the rotation', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        $this->application = Application::factory()
            ->withPostLogoutRedirect('https://app.example.test/signed-out')
            ->create(['redirect_uris' => ['https://app.example.test/auth/callback']]);
    });

    test('still signs the user out straight away while its key is retained', function () {
        withoutKeyFiles(function () {
            $oldPublic = signWithFreshPair();
            $hint = idTokenHint($this->user, $this->application);

            signWithFreshPair();
            retainPublicKey($oldPublic);

            $this->actingAs($this->user)->get(route('oidc.logout', [
                'id_token_hint' => $hint,
                'post_logout_redirect_uri' => 'https://app.example.test/signed-out',
            ]))->assertRedirect('https://app.example.test/signed-out');

            $this->assertGuest();
        });
    });

    test('asks first once its key is no longer published', function () {
        withoutKeyFiles(function () {
            signWithFreshPair();
            $hint = idTokenHint($this->user, $this->application);

            signWithFreshPair();

            $this->actingAs($this->user)->get(route('oidc.logout', [
                'id_token_hint' => $hint,
                'post_logout_redirect_uri' => 'https://app.example.test/signed-out',
            ]))->assertOk()->assertInertia(fn (Assert $page) => $page->component('oauth/Logout'));

            $this->assertAuthenticatedAs($this->user);
        });
    });
});
