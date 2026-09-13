<?php

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;

/**
 * How every adapter works out which client a protocol request comes from.
 */
beforeEach(function () {
    $this->application = Application::factory()->withSecret('contract-secret')->create();
});

test('a confidential client authenticates with HTTP Basic', function (string $adapter) {
    $request = Request::create('/oauth/introspect', 'POST', server: [
        'HTTP_AUTHORIZATION' => 'Basic '.base64_encode($this->application->id.':contract-secret'),
    ]);

    expect(oidcAdapter($adapter)->clients()->authenticate($request)?->id)->toBe($this->application->id);
})->with('oidc adapters');

test('a confidential client authenticates with credentials in the body', function (string $adapter) {
    $request = Request::create('/oauth/introspect', 'POST', [
        'client_id' => $this->application->id,
        'client_secret' => 'contract-secret',
    ]);

    expect(oidcAdapter($adapter)->clients()->authenticate($request)?->id)->toBe($this->application->id);
})->with('oidc adapters');

test('a wrong secret or an unknown client does not authenticate', function (string $adapter) {
    $clients = oidcAdapter($adapter)->clients();

    expect($clients->authenticate(Request::create('/oauth/introspect', 'POST', [
        'client_id' => $this->application->id,
        'client_secret' => 'not-the-secret',
    ])))->toBeNull()
        ->and($clients->authenticate(Request::create('/oauth/introspect', 'POST', [
            'client_id' => (string) Str::uuid(),
            'client_secret' => 'contract-secret',
        ])))->toBeNull();
})->with('oidc adapters');

test('a public client authenticates with its client id alone', function (string $adapter) {
    $public = app(ClientRepository::class)
        ->createAuthorizationCodeGrantClient('Mobile App', ['https://mobile.example.com/callback'], confidential: false);

    $request = Request::create('/oauth/introspect', 'POST', ['client_id' => $public->id]);

    expect(oidcAdapter($adapter)->clients()->authenticate($request)?->id)->toBe($public->id);
})->with('oidc adapters');
