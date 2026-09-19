<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Application;
use App\Models\User;
use App\Oidc\ConsentScreen;
use App\Services\Settings;

/**
 * The consent screen is what an untrusted application's users actually see.
 * ConsentTest covers when it is shown; this covers what it does.
 */
const CONSENT_SCREEN_REDIRECT_URI = 'https://consent-screen.example.com/auth/callback';

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Alice Smith']);

    $this->application = Application::factory()
        ->withSecret('consent-secret')
        ->create([
            'name' => 'Reporting',
            'description' => 'Company reporting',
            'redirect_uris' => [CONSENT_SCREEN_REDIRECT_URI],
        ]);
});

test('the consent screen names the application and what it is asking for', function () {
    $response = authorizationRequest($this->user, $this->application, ['scope' => 'openid email', 'state' => 'opaque-state']);

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('oauth/Authorize')
        ->where('application.name', 'Reporting')
        ->where('application.description', 'Company reporting')
        ->has('scopes', 2)
        ->has('authToken'));
});

test('the consent screen describes each scope rather than naming it', function () {
    $response = authorizationRequest($this->user, $this->application, ['scope' => 'openid email']);

    $response->assertInertia(fn ($page) => $page->where(
        'scopes',
        fn ($scopes) => collect($scopes)->pluck('description')->every(
            fn (string $description): bool => $description !== '' && ! str_contains($description, 'openid'),
        ),
    ));
});

test('approving issues an authorization code', function () {
    [$verifier, $challenge] = pkcePair();

    $response = authorizationRequest($this->user, $this->application, [
        'scope' => 'openid email',
        'state' => 'opaque-state',
        'code_challenge' => $challenge,
    ]);

    $authToken = $response->viewData('page')['props']['authToken'];

    $approval = $this->actingAs($this->user)->post('/oauth/authorize', [
        'auth_token' => $authToken,
        'state' => 'opaque-state',
    ]);

    $approval->assertRedirectContains(CONSENT_SCREEN_REDIRECT_URI);

    parse_str(parse_url($approval->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query)->toHaveKey('code');

    $tokens = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->application->id,
        'client_secret' => 'consent-secret',
        'redirect_uri' => CONSENT_SCREEN_REDIRECT_URI,
        'code_verifier' => $verifier,
        'code' => $query['code'],
    ]);

    $tokens->assertOk()->assertJsonStructure(['access_token', 'id_token']);
});

test('denying sends the application an access_denied error instead of a code', function () {
    $response = authorizationRequest($this->user, $this->application, ['scope' => 'openid email', 'state' => 'opaque-state']);

    $authToken = $response->viewData('page')['props']['authToken'];

    $denial = $this->actingAs($this->user)->delete('/oauth/authorize', [
        'auth_token' => $authToken,
        'state' => 'opaque-state',
    ]);

    $denial->assertRedirectContains(CONSENT_SCREEN_REDIRECT_URI);

    parse_str(parse_url($denial->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query)->not->toHaveKey('code')
        ->and($query['error'])->toBe('access_denied');
});

test('a decision made on the consent page leaves for the application with a full page load', function (string $method, string $expected) {
    $response = authorizationRequest($this->user, $this->application, ['scope' => 'openid email', 'state' => 'opaque-state']);

    $decision = $this->actingAs($this->user)->withHeader('X-Inertia', 'true')->{$method}('/oauth/authorize', [
        'auth_token' => $response->viewData('page')['props']['authToken'],
        'state' => 'opaque-state',
    ]);

    $location = (string) $decision->assertStatus(409)->headers->get('X-Inertia-Location');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($location)->toStartWith(CONSENT_SCREEN_REDIRECT_URI)
        ->and($query)->toHaveKey($expected);
})->with([
    'approving' => ['post', 'code'],
    'denying' => ['delete', 'error'],
]);

test('signing in to an application that skips consent leaves for it with a full page load', function () {
    $this->application->forceFill(['skips_authorization' => true])->save();

    /*
     * The sign-in page submits through Inertia, and its request follows the
     * redirect back to the authorization endpoint, which sends it straight on
     * to the application.
     */
    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
    ]);

    $location = (string) authorizationRequest($this->user, $this->application, ['state' => 'opaque-state'])
        ->assertStatus(409)
        ->headers->get('X-Inertia-Location');

    expect($location)->toStartWith(CONSENT_SCREEN_REDIRECT_URI)->toContain('code=');
});

test('an approval cannot be replayed with a stale token', function () {
    $response = authorizationRequest($this->user, $this->application, ['scope' => 'openid email', 'state' => 'opaque-state']);

    $authToken = $response->viewData('page')['props']['authToken'];

    $this->actingAs($this->user)
        ->post('/oauth/authorize', ['auth_token' => $authToken, 'state' => 'opaque-state'])
        ->assertRedirectContains(CONSENT_SCREEN_REDIRECT_URI);

    /*
     * The token is pulled from the session when it is used, so a second
     * approval has nothing to match against.
     */
    $this->actingAs($this->user)
        ->post('/oauth/authorize', ['auth_token' => $authToken, 'state' => 'opaque-state'])
        ->assertForbidden();
});

test('an approval carrying someone else\'s token is refused', function () {
    authorizationRequest($this->user, $this->application, ['scope' => 'openid email']);

    $this->actingAs($this->user)
        ->post('/oauth/authorize', ['auth_token' => 'not-the-token', 'state' => 'opaque-state'])
        ->assertForbidden();
});

test('a guest is sent to sign in before being asked to consent', function () {
    authorizationRequest(null, $this->application)->assertRedirect(route('login'));
});

describe('as worded on the Consent tab', function () {
    test('the heading names the application, and the message is its description', function () {
        authorizationRequest($this->user, $this->application)
            ->assertInertia(fn ($page) => $page
                ->where('consent.heading', 'Continue to Reporting')
                ->where('consent.message', 'Company reporting')
                ->where('consent.privacyUrl', null)
                ->where('consent.termsUrl', null));
    });

    test('an application with no description gets a general message', function () {
        $this->application->forceFill(['description' => null])->save();

        authorizationRequest($this->user, $this->application)
            ->assertInertia(fn ($page) => $page->where('consent.message', ConsentScreen::DEFAULT_MESSAGE));
    });

    test('an administrator\'s wording replaces the defaults', function () {
        app(Settings::class)->put([
            'consent_heading' => 'Sign in to {application}',
            'consent_message' => '{application} is run by IT.',
            'consent_scope_descriptions' => ['email' => 'Your work address'],
            'consent_privacy_url' => 'https://example.com/privacy',
            'consent_terms_url' => 'https://example.com/terms',
        ]);

        authorizationRequest($this->user, $this->application, ['scope' => 'openid email'])
            ->assertInertia(fn ($page) => $page
                ->where('consent.heading', 'Sign in to Reporting')
                ->where('consent.message', 'Reporting is run by IT.')
                ->where('scopes', fn ($scopes) => collect($scopes)->firstWhere('id', 'email')['description'] === 'Your work address')
                ->where('consent.privacyUrl', 'https://example.com/privacy')
                ->where('consent.termsUrl', 'https://example.com/terms'));
    });

    test('the signed-in account offers a way to sign in as somebody else, unless it is hidden', function () {
        authorizationRequest($this->user, $this->application)
            ->assertInertia(fn ($page) => $page->where(
                'consent.switchAccountUrl',
                fn (string $url): bool => str_contains($url, '/oauth/authorize?') && str_contains($url, 'prompt=login'),
            ));

        app(Settings::class)->put(['consent_show_account' => false]);

        authorizationRequest($this->user, $this->application)
            ->assertInertia(fn ($page) => $page->where('consent.switchAccountUrl', null));
    });

    test('an approval already given is remembered, unless the Consent tab says to ask every time', function (bool $remember) {
        app(Settings::class)->put(['consent_remember_approvals' => $remember]);

        [$verifier, $challenge] = pkcePair();

        $consent = authorizationRequest($this->user, $this->application, ['scope' => 'openid email', 'code_challenge' => $challenge]);

        $approval = $this->actingAs($this->user)->post('/oauth/authorize', [
            'auth_token' => $consent->viewData('page')['props']['authToken'],
        ]);

        redeemCode($this->application, $approval, $verifier);

        $again = authorizationRequest($this->user, $this->application, ['scope' => 'openid email']);

        $remember
            ? $again->assertRedirectContains(CONSENT_SCREEN_REDIRECT_URI)
            : $again->assertOk()->assertInertia(fn ($page) => $page->component('oauth/Authorize'));
    })->with(['remembered' => true, 'asked every time' => false]);
});
