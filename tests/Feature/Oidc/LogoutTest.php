<?php

use App\Models\Application;
use App\Models\User;
use App\Oidc\Contracts\IdTokenRequest;
use App\Oidc\Contracts\IssuesIdTokens;
use Inertia\Testing\AssertableInertia as Assert;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;

/**
 * RP-initiated logout. See App\Oidc\Http\Controllers\LogoutController.
 */
beforeEach(function () {
    $this->user = User::factory()->create();

    $this->application = Application::factory()
        ->withPostLogoutRedirect('https://app.example.test/signed-out')
        ->create(['redirect_uris' => ['https://app.example.test/auth/callback']]);
});

/**
 * An ID Token this server issued, sent back as a client would.
 */
function idTokenHint(User $user, Application $application): string
{
    return app(IssuesIdTokens::class)->issue(new IdTokenRequest($user, $application->id, ['openid']));
}

describe('with an id_token_hint for the signed-in user', function () {
    test('it signs out and returns to the registered destination, echoing state', function () {
        $this->actingAs($this->user)->get(route('oidc.logout', [
            'id_token_hint' => idTokenHint($this->user, $this->application),
            'post_logout_redirect_uri' => 'https://app.example.test/signed-out',
            'state' => 'opaque-value',
        ]))->assertRedirect('https://app.example.test/signed-out?state=opaque-value');

        $this->assertGuest();
    });

    test('an expired hint still counts', function () {
        $this->travel(-1)->days();
        $hint = idTokenHint($this->user, $this->application);
        $this->travelBack();

        $this->actingAs($this->user)->get(route('oidc.logout', [
            'id_token_hint' => $hint,
            'post_logout_redirect_uri' => 'https://app.example.test/signed-out',
        ]))->assertRedirect('https://app.example.test/signed-out');
    });

    test('a destination the client did not register is dropped, and the user is still signed out', function (string $destination) {
        $this->actingAs($this->user)->get(route('oidc.logout', [
            'id_token_hint' => idTokenHint($this->user, $this->application),
            'post_logout_redirect_uri' => $destination,
        ]))->assertRedirect(route('home'));

        $this->assertGuest();
    })->with([
        'a redirect URI, which receives codes' => 'https://app.example.test/auth/callback',
        'a path beneath a registered URI' => 'https://app.example.test/signed-out/elsewhere',
        'another site' => 'https://attacker.example.com/',
    ]);

    test('a hint issued to another client consults only that client\'s list', function () {
        $other = Application::factory()->create(['post_logout_redirect_uris' => []]);

        $this->actingAs($this->user)->get(route('oidc.logout', [
            'id_token_hint' => idTokenHint($this->user, $other),
            'post_logout_redirect_uri' => 'https://app.example.test/signed-out',
        ]))->assertRedirect(route('home'));
    });

    test('a client_id that disagrees with the hint sends the browser nowhere', function () {
        $this->actingAs($this->user)->get(route('oidc.logout', [
            'id_token_hint' => idTokenHint($this->user, $this->application),
            'client_id' => Application::factory()->withPostLogoutRedirect('https://app.example.test/signed-out')->create()->id,
            'post_logout_redirect_uri' => 'https://app.example.test/signed-out',
        ]))->assertRedirect(route('home'));
    });
});

test('a request posted from a relying party\'s page is sent on as the same GET', function () {
    $parameters = [
        'id_token_hint' => idTokenHint($this->user, $this->application),
        'post_logout_redirect_uri' => 'https://app.example.test/signed-out',
    ];

    $this->actingAs($this->user)->post(route('oidc.logout'), $parameters)
        ->assertStatus(303)
        ->assertRedirect(route('oidc.logout', $parameters));

    $this->assertAuthenticatedAs($this->user);
});

describe('without proof of where the request comes from', function () {
    test('the user is asked before being signed out', function (?string $hint) {
        $this->actingAs($this->user)->get(route('oidc.logout', array_filter([
            'id_token_hint' => $hint,
            'post_logout_redirect_uri' => 'https://app.example.test/signed-out',
        ])))->assertOk()->assertInertia(fn (Assert $page) => $page->component('oauth/Logout'));

        $this->assertAuthenticatedAs($this->user);
    })->with([
        'no hint' => fn () => null,
        'a malformed hint' => fn () => 'not-a-token',
        'a hint this server did not sign' => fn () => (new Builder(new JoseEncoder, ChainedFormatter::default()))
            ->permittedFor($this->application->id)
            ->issuedBy(config('sso.issuer'))
            ->relatedTo((string) $this->user->id)
            ->getToken(new Sha256, InMemory::plainText(str_repeat('a', 32)))
            ->toString(),
        'a hint for somebody else' => fn () => idTokenHint(User::factory()->create(), $this->application),
    ]);

    test('confirming signs out and returns to the destination the named client registered', function () {
        $parameters = [
            'client_id' => $this->application->id,
            'post_logout_redirect_uri' => 'https://app.example.test/signed-out',
            'state' => 'opaque-value',
        ];

        $this->actingAs($this->user)
            ->get(route('oidc.logout', $parameters))
            ->assertInertia(fn (Assert $page) => $page
                ->component('oauth/Logout')
                ->where('application.name', $this->application->name)
                ->where('parameters', $parameters));

        $this->post(route('oidc.logout.confirm'), $parameters)
            ->assertRedirect('https://app.example.test/signed-out?state=opaque-value');

        $this->assertGuest();
    });

    test('confirming without a client returns home', function () {
        $this->actingAs($this->user)->post(route('oidc.logout.confirm'))->assertRedirect(route('home'));

        $this->assertGuest();
    });

    test('a guest has nothing to confirm, and goes where the named client registered', function () {
        $this->get(route('oidc.logout', [
            'client_id' => $this->application->id,
            'post_logout_redirect_uri' => 'https://app.example.test/signed-out',
        ]))->assertRedirect('https://app.example.test/signed-out');
    });
});
