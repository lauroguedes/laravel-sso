<?php

use App\Models\User;
use Illuminate\Http\Request;

/**
 * What every adapter does when a relying party signs the user out.
 */
test('ending a session signs the user out and sends them home', function (string $adapter) {
    $this->actingAs(User::factory()->create());

    $request = Request::create('/oauth/logout');
    $request->setLaravelSession($this->app['session.store']);

    $response = oidcAdapter($adapter)->sessions()->endSession($request);

    expect($response->isRedirect(url('/')))->toBeTrue();
    $this->assertGuest();
})->with('oidc adapters');
