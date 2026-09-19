<?php

use App\Models\Application;
use App\Oidc\Contracts\AuthenticatesClients;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;

/**
 * How a protocol request is matched to the client it comes from.
 */
beforeEach(function () {
    $this->application = Application::factory()->withSecret('contract-secret')->create();
});

test('a confidential client authenticates with HTTP Basic', function () {
    $request = Request::create('/oauth/introspect', 'POST', server: [
        'HTTP_AUTHORIZATION' => 'Basic '.base64_encode($this->application->id.':contract-secret'),
    ]);

    expect(app(AuthenticatesClients::class)->authenticate($request)?->id)->toBe($this->application->id);
});

test('a confidential client authenticates with credentials in the body', function () {
    $request = Request::create('/oauth/introspect', 'POST', [
        'client_id' => $this->application->id,
        'client_secret' => 'contract-secret',
    ]);

    expect(app(AuthenticatesClients::class)->authenticate($request)?->id)->toBe($this->application->id);
});

test('a wrong secret or an unknown client does not authenticate', function () {
    $clients = app(AuthenticatesClients::class);

    expect($clients->authenticate(Request::create('/oauth/introspect', 'POST', [
        'client_id' => $this->application->id,
        'client_secret' => 'not-the-secret',
    ])))->toBeNull()
        ->and($clients->authenticate(Request::create('/oauth/introspect', 'POST', [
            'client_id' => (string) Str::uuid(),
            'client_secret' => 'contract-secret',
        ])))->toBeNull();
});

test('a public client authenticates with its client id alone', function () {
    $public = app(ClientRepository::class)
        ->createAuthorizationCodeGrantClient('Mobile App', ['https://mobile.example.com/callback'], confidential: false);

    $request = Request::create('/oauth/introspect', 'POST', ['client_id' => $public->id]);

    expect(app(AuthenticatesClients::class)->authenticate($request)?->id)->toBe($public->id);
});
