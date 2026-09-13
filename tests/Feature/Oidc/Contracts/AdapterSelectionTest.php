<?php

use App\Oidc\Contracts\DiscoversProvider;
use App\Oidc\Contracts\OidcAdapter;
use App\Oidc\OidcManager;

/**
 * Choosing an implementation is a change of configuration: the endpoints ask
 * for ports, and the ports come from whichever adapter is named.
 */
test('an adapter registered and named in configuration serves the endpoints', function () {
    $discovery = Mockery::mock(DiscoversProvider::class);
    $discovery->shouldReceive('metadata')->andReturn(['issuer' => 'https://swapped.example.com']);

    $adapter = Mockery::mock(OidcAdapter::class);
    $adapter->shouldReceive('discovery')->andReturn($discovery);

    app(OidcManager::class)->extend('swapped', fn () => $adapter);
    config(['oidc.driver' => 'swapped']);

    $this->getJson('/.well-known/openid-configuration')
        ->assertOk()
        ->assertExactJson(['issuer' => 'https://swapped.example.com']);
});
