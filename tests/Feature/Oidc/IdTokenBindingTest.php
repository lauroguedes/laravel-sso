<?php

use App\Models\Application;
use App\Models\User;

/**
 * What an ID Token repeats from the authorization it answers.
 *
 * The nonce is the one the client sent to /oauth/authorize, auth_time is when
 * the user actually signed in, and exp follows the ID Token lifetime from App
 * settings (OpenID Connect Core sections 2, 3.1.2.1 and 12.2).
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->application = Application::factory()->trusted()->withSecret('binding-secret')->create();
});

describe('nonce', function () {
    test('the id token carries the nonce sent to the authorization request', function () {
        $tokens = issueTokens($this->user, $this->application, 'openid', ['nonce' => 'nonce-from-authorize']);

        expect(idTokenClaims($tokens['id_token'])->get('nonce'))->toBe('nonce-from-authorize');
    });

    test('a nonce sent only to the token endpoint is not repeated', function () {
        [$verifier, $challenge] = pkcePair();

        $authorization = authorizationRequest($this->user, $this->application, ['code_challenge' => $challenge]);

        $tokens = redeemCode($this->application, $authorization, $verifier, ['nonce' => 'nonce-from-token-request']);

        expect(idTokenClaims($tokens['id_token'])->has('nonce'))->toBeFalse();
    });

    test('approving the consent screen carries the nonce of the authorization being approved', function () {
        $application = Application::factory()->withSecret('consent-secret')->create();
        [$verifier, $challenge] = pkcePair();

        authorizationRequest($this->user, $application, ['nonce' => 'superseded-authorization']);
        $consent = authorizationRequest($this->user, $application, ['nonce' => 'approved-authorization', 'code_challenge' => $challenge]);

        $approval = $this->actingAs($this->user)->post('/oauth/authorize', [
            'auth_token' => $consent->viewData('page')['props']['authToken'],
        ]);

        expect(idTokenClaims(redeemCode($application, $approval, $verifier)['id_token'])->get('nonce'))
            ->toBe('approved-authorization');
    });
});

describe('auth_time', function () {
    test('auth_time is when the user signed in, and a refreshed id token keeps it without a nonce', function () {
        signInWithPassword($this->user);
        $signedInAt = now()->getTimestamp();

        $this->travel(5)->minutes();

        $tokens = issueTokens($this->user, $this->application, 'openid', ['nonce' => 'first-sign-in']);

        $refreshed = $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->application->id,
            'client_secret' => 'binding-secret',
            'refresh_token' => $tokens['refresh_token'],
        ])->assertOk()->json('id_token');

        expect(idTokenClaims($tokens['id_token'])->get('auth_time'))->toBe($signedInAt)
            ->and(idTokenClaims($refreshed)->get('auth_time'))->toBe($signedInAt)
            ->and(idTokenClaims($refreshed)->has('nonce'))->toBeFalse();
    });

    test('a session with no recorded sign-in gives no auth_time', function () {
        expect(idTokenClaims(issueTokens($this->user, $this->application, 'openid')['id_token'])->has('auth_time'))
            ->toBeFalse();
    });
});

test('an id token expires after the id token lifetime, not with the access token', function () {
    config(['oidc.tokens.id_token_ttl' => 300]);

    $claims = idTokenClaims(issueTokens($this->user, $this->application, 'openid')['id_token']);

    expect($claims->get('exp')->getTimestamp() - $claims->get('iat')->getTimestamp())->toBe(300);
});

describe('max_age', function () {
    test('a sign-in within max_age is not asked for again', function () {
        signInWithPassword($this->user);
        $this->travel(30)->seconds();

        authorizationRequest(null, $this->application, ['max_age' => '60'])
            ->assertRedirectContains($this->application->redirect_uris[0]);

        $this->assertAuthenticatedAs($this->user);
    });

    test('an older sign-in is signed out and sent back to sign in', function () {
        signInWithPassword($this->user);
        $this->travel(10)->minutes();

        authorizationRequest(null, $this->application, ['max_age' => '60'])->assertRedirect(route('login'));

        $this->assertGuest();
    });

    test('with prompt=none an older sign-in gets login_required instead', function () {
        signInWithPassword($this->user);
        $this->travel(10)->minutes();

        authorizationRequest(null, $this->application, ['max_age' => '60', 'prompt' => 'none'])
            ->assertRedirectContains('error=login_required');

        $this->assertAuthenticatedAs($this->user);
    });

    test('max_age of zero asks for a new sign-in once, not in a loop', function () {
        signInWithPassword($this->user);
        $this->travel(1)->seconds();

        authorizationRequest(null, $this->application, ['max_age' => '0'])->assertRedirect(route('login'));

        signInWithPassword($this->user);
        $this->travel(2)->seconds();

        authorizationRequest(null, $this->application, ['max_age' => '0'])
            ->assertRedirectContains($this->application->redirect_uris[0]);
    });
});
