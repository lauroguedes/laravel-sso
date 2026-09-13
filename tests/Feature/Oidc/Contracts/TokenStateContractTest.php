<?php

use App\Models\Application;
use App\Models\User;
use App\Oidc\Contracts\IntrospectsTokens;
use App\Oidc\Contracts\RevokesTokens;

/**
 * How introspection and revocation answer, called directly. Who may call them
 * is pinned at the endpoints, in TokenStateSecurityTest.
 */
beforeEach(function () {
    $this->user = User::factory()->create();

    $this->application = Application::factory()->trusted()->withSecret('contract-secret')->create();

    $this->tokens = issueTokens($this->user, $this->application);
});

test('an access token is described to the client holding it', function () {
    expect(app(IntrospectsTokens::class)->introspect($this->application, $this->tokens['access_token'], null))
        ->toMatchArray([
            'active' => true,
            'client_id' => $this->application->id,
            'sub' => (string) $this->user->id,
        ]);
});

test('a missing or unknown token is only inactive', function () {
    $introspection = app(IntrospectsTokens::class);

    expect($introspection->introspect($this->application, null, null))->toBe(['active' => false])
        ->and($introspection->introspect($this->application, 'not-a-token', null))->toBe(['active' => false]);
});

test('a revoked access token is inactive', function () {
    app(RevokesTokens::class)->revoke($this->application, $this->tokens['access_token'], null);

    expect(app(IntrospectsTokens::class)->introspect($this->application, $this->tokens['access_token'], null))
        ->toBe(['active' => false]);
});

test('a client cannot revoke a token issued to another client', function () {
    app(RevokesTokens::class)->revoke(Application::factory()->create(), $this->tokens['access_token'], null);

    expect(app(IntrospectsTokens::class)->introspect($this->application, $this->tokens['access_token'], null))
        ->toMatchArray(['active' => true]);
});
