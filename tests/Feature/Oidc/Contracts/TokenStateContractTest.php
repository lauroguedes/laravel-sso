<?php

use App\Models\Application;
use App\Models\User;

/**
 * How every adapter answers introspection and revocation.
 *
 * Only what every implementation shares is here. Anything an adapter is free
 * to handle differently is pinned at the endpoints instead.
 */
beforeEach(function () {
    $this->user = User::factory()->create();

    $this->application = Application::factory()->trusted()->withSecret('contract-secret')->create();

    $this->tokens = issueTokens($this->user, $this->application);
});

test('an access token is described to the client holding it', function (string $adapter) {
    expect(oidcAdapter($adapter)->introspection()->introspect($this->application, $this->tokens['access_token'], null))
        ->toMatchArray([
            'active' => true,
            'client_id' => $this->application->id,
            'sub' => (string) $this->user->id,
        ]);
})->with('oidc adapters');

test('a missing or unknown token is only inactive', function (string $adapter) {
    $introspection = oidcAdapter($adapter)->introspection();

    expect($introspection->introspect($this->application, null, null))->toBe(['active' => false])
        ->and($introspection->introspect($this->application, 'not-a-token', null))->toBe(['active' => false]);
})->with('oidc adapters');

test('a revoked access token is inactive', function (string $adapter) {
    $oidc = oidcAdapter($adapter);

    $oidc->revocation()->revoke($this->application, $this->tokens['access_token'], null);

    expect($oidc->introspection()->introspect($this->application, $this->tokens['access_token'], null))
        ->toBe(['active' => false]);
})->with('oidc adapters');

test('a client cannot revoke a token issued to another client', function (string $adapter) {
    $oidc = oidcAdapter($adapter);

    $oidc->revocation()->revoke(Application::factory()->create(), $this->tokens['access_token'], null);

    expect($oidc->introspection()->introspect($this->application, $this->tokens['access_token'], null))
        ->toMatchArray(['active' => true]);
})->with('oidc adapters');
