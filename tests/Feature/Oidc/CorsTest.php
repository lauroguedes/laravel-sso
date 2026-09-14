<?php

use Illuminate\Testing\TestResponse;

/**
 * Which browser applications may call the protocol endpoints from their own
 * origin, and which endpoints answer them.
 */
beforeEach(function () {
    /*
     * Two origins, so the answer names the one that asked. With a single
     * origin the underlying library always names it, and the browser does the
     * refusing.
     */
    config()->set('cors.allowed_origins', ['https://spa.example.test', 'http://localhost:3000']);
});

/**
 * Ask the way a browser does before a cross-origin request.
 */
function preflight(string $uri, string $method, string $origin = 'https://spa.example.test'): TestResponse
{
    return test()->call('OPTIONS', $uri, server: [
        'HTTP_ORIGIN' => $origin,
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $method,
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
    ]);
}

test('a listed origin may call the endpoints a browser application needs', function (string $uri, string $method) {
    preflight($uri, $method)
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', 'https://spa.example.test');
})->with([
    'discovery' => ['/.well-known/openid-configuration', 'GET'],
    'key set' => ['/.well-known/jwks.json', 'GET'],
    'token' => ['/oauth/token', 'POST'],
    'userinfo' => ['/oauth/userinfo', 'GET'],
    'revocation' => ['/oauth/revoke', 'POST'],
]);

test('the answer itself carries the origin, so the browser lets the application read it', function () {
    $this->getJson('/.well-known/openid-configuration', ['Origin' => 'https://spa.example.test'])
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', 'https://spa.example.test');
});

test('an origin that is not listed is not answered', function () {
    preflight('/oauth/token', 'POST', 'https://elsewhere.example.test')
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});

test('introspection and the interface never answer another origin', function (string $uri, string $method) {
    preflight($uri, $method)->assertHeaderMissing('Access-Control-Allow-Origin');
})->with([
    'introspection' => ['/oauth/introspect', 'POST'],
    'sign-in' => ['/login', 'POST'],
]);
