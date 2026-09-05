<?php

use App\Models\Application;
use App\Models\User;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;

/**
 * Mint an id_token_hint naming a client.
 *
 * The hint is never signature-verified — the specification only uses it to
 * identify the client — so the key here is irrelevant to what is being tested.
 */
function idTokenHint(string $clientId): string
{
    return (new Builder(new JoseEncoder, ChainedFormatter::default()))
        ->permittedFor($clientId)
        ->issuedBy(config('sso.issuer'))
        ->issuedAt(now()->toDateTimeImmutable())
        ->getToken(new Sha256, InMemory::plainText(str_repeat('a', 32)))
        ->toString();
}

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->application = Application::factory()
        ->withPostLogoutRedirect('https://app.example.test/signed-out')
        ->create(['redirect_uris' => ['https://app.example.test/auth/callback']]);
});

test('it ends the session', function () {
    $this->actingAs($this->user)->get(route('oidc.logout'));

    $this->assertGuest();
});

test('it redirects to a registered post-logout URI', function () {
    $response = $this->actingAs($this->user)->get(route('oidc.logout', [
        'id_token_hint' => idTokenHint($this->application->id),
        'post_logout_redirect_uri' => 'https://app.example.test/signed-out',
    ]));

    $this->assertGuest();
    $response->assertRedirect('https://app.example.test/signed-out');
});

test('a redirect URI is not thereby a post-logout URI', function () {
    /*
     * The two lists are separate on purpose: redirect URIs receive
     * authorization codes, so an operator should be registering as few as
     * possible rather than widening them to cover logout landing pages.
     */
    $response = $this->actingAs($this->user)->get(route('oidc.logout', [
        'id_token_hint' => idTokenHint($this->application->id),
        'post_logout_redirect_uri' => 'https://app.example.test/auth/callback',
    ]));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('a path underneath a registered URI is refused', function () {
    /*
     * Matched exactly. Prefix matching would make every path under a
     * registered origin an open redirect this server vouches for.
     */
    $response = $this->actingAs($this->user)->get(route('oidc.logout', [
        'id_token_hint' => idTokenHint($this->application->id),
        'post_logout_redirect_uri' => 'https://app.example.test/signed-out/elsewhere',
    ]));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('an unregistered destination is refused', function () {
    $response = $this->actingAs($this->user)->get(route('oidc.logout', [
        'id_token_hint' => idTokenHint($this->application->id),
        'post_logout_redirect_uri' => 'https://attacker.example.com/',
    ]));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('a destination with no hint to vouch for it is refused', function () {
    $response = $this->actingAs($this->user)->get(route('oidc.logout', [
        'post_logout_redirect_uri' => 'https://app.example.test/signed-out',
    ]));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('a hint naming another client does not borrow this one list', function () {
    $other = Application::factory()->create(['post_logout_redirect_uris' => []]);

    $response = $this->actingAs($this->user)->get(route('oidc.logout', [
        'id_token_hint' => idTokenHint($other->id),
        'post_logout_redirect_uri' => 'https://app.example.test/signed-out',
    ]));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('state is echoed back to the landing page', function () {
    $response = $this->actingAs($this->user)->get(route('oidc.logout', [
        'id_token_hint' => idTokenHint($this->application->id),
        'post_logout_redirect_uri' => 'https://app.example.test/signed-out',
        'state' => 'opaque-value',
    ]));

    $response->assertRedirect('https://app.example.test/signed-out?state=opaque-value');
});

test('a malformed hint is survivable', function () {
    $response = $this->actingAs($this->user)->get(route('oidc.logout', [
        'id_token_hint' => 'not-a-token',
        'post_logout_redirect_uri' => 'https://app.example.test/signed-out',
    ]));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('the session ends even when the destination is refused', function () {
    /*
     * The user asked to be logged out. Refusing the whole request over a
     * redirect the client got wrong would leave the session alive, which is
     * the more dangerous failure.
     */
    $this->actingAs($this->user)->get(route('oidc.logout', [
        'post_logout_redirect_uri' => 'https://attacker.example.com/',
    ]));

    $this->assertGuest();
});
