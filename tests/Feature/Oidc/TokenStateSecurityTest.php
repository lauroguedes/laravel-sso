<?php

use App\Models\Application;
use App\Models\User;

/**
 * Who may ask about a token or give one up, and what a presented token proves.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->application = Application::factory()->trusted()->withSecret('state-secret')->create();
    $this->tokens = issueTokens($this->user, $this->application);
});

describe('client authentication', function () {
    test('a disabled application is refused', function (string $endpoint) {
        $disabled = Application::factory()->disabled()->withSecret('disabled-secret')->create();

        $this->withBasicAuth($disabled->id, 'disabled-secret')
            ->postJson($endpoint, ['token' => $this->tokens['access_token']])
            ->assertUnauthorized()
            ->assertJson(['error' => 'invalid_client']);
    })->with(['/oauth/introspect', '/oauth/revoke']);

    test('a client id that is not a client id is refused rather than looked up', function (string $endpoint) {
        $this->postJson($endpoint, [
            'grant_type' => 'client_credentials',
            'client_id' => 'not-a-client-id',
            'client_secret' => 'state-secret',
            'token' => $this->tokens['access_token'],
        ])->assertUnauthorized()->assertJson(['error' => 'invalid_client']);
    })->with(['/oauth/introspect', '/oauth/revoke', '/oauth/token']);
});

describe('introspection', function () {
    test('a public client may not introspect', function () {
        $this->postJson('/oauth/introspect', [
            'client_id' => Application::factory()->asPublic()->create()->id,
            'token' => $this->tokens['access_token'],
        ])->assertUnauthorized()
            ->assertJson(['error' => 'invalid_client'])
            ->assertJsonMissingPath('active');
    });

    test('a token without this server\'s signature is inactive, and so is a bare token id', function (Closure $present) {
        $this->withBasicAuth($this->application->id, 'state-secret')
            ->postJson('/oauth/introspect', ['token' => $present($this->tokens['access_token'])])
            ->assertOk()
            ->assertExactJson(['active' => false]);
    })->with([
        'a tampered signature' => fn (string $token): string => substr($token, 0, -8).'AAAAAAAA',
        'the token id alone' => fn (string $token): string => idTokenClaims($token)->get('jti'),
    ]);

    test('the email address is disclosed only to a token granted the email scope', function () {
        $tokens = issueTokens($this->user, $this->application, 'openid');

        $this->withBasicAuth($this->application->id, 'state-secret')
            ->postJson('/oauth/introspect', ['token' => $tokens['access_token']])
            ->assertOk()
            ->assertJson(['active' => true, 'sub' => (string) $this->user->id])
            ->assertJsonMissingPath('username');
    });

    test('a refresh token is described as one, with or without a hint', function (?string $hint) {
        $this->withBasicAuth($this->application->id, 'state-secret')
            ->postJson('/oauth/introspect', array_filter([
                'token' => $this->tokens['refresh_token'],
                'token_type_hint' => $hint,
            ]))
            ->assertOk()
            ->assertJson([
                'active' => true,
                'token_type' => 'refresh_token',
                'client_id' => $this->application->id,
                'sub' => (string) $this->user->id,
            ]);
    })->with(['hinted' => 'refresh_token', 'unhinted' => null]);

    test('an unknown token type hint is ignored rather than refused', function () {
        $this->withBasicAuth($this->application->id, 'state-secret')
            ->postJson('/oauth/introspect', [
                'token' => $this->tokens['access_token'],
                'token_type_hint' => 'id_token',
            ])
            ->assertOk()
            ->assertJson(['active' => true]);
    });
});

describe('revocation', function () {
    test('a public client may revoke its own token', function () {
        $public = Application::factory()->asPublic()->trusted()->create();
        $tokens = issueTokens($this->user, $public);

        $this->postJson('/oauth/revoke', [
            'client_id' => $public->id,
            'token' => $tokens['access_token'],
        ])->assertOk();

        $this->getJson('/oauth/userinfo', ['Authorization' => 'Bearer '.$tokens['access_token']])->assertUnauthorized();
    });

    test('a bare token id revokes nothing', function () {
        $this->withBasicAuth($this->application->id, 'state-secret')
            ->postJson('/oauth/revoke', ['token' => idTokenClaims($this->tokens['access_token'])->get('jti')])
            ->assertOk();

        $this->getJson('/oauth/userinfo', ['Authorization' => 'Bearer '.$this->tokens['access_token']])->assertOk();
    });
});
