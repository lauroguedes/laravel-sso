<?php

use App\Models\Application;
use App\Models\User;

/**
 * A restricted application admits only the users an administrator has granted
 * access to. An unrestricted one admits anybody with an account here.
 */
const ENFORCEMENT_REDIRECT_URI = 'https://restricted.example.com/auth/callback';

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->application = Application::factory()->trusted()->create([
        'name' => 'Reporting',
        'redirect_uris' => [ENFORCEMENT_REDIRECT_URI],
    ]);
});

test('an unrestricted application admits any authenticated user', function () {
    expect($this->application->restricts_access)->toBeFalse();

    authorizationRequest($this->user, $this->application)
        ->assertRedirectContains(ENFORCEMENT_REDIRECT_URI);
});

test('a restricted application turns away a user without access', function () {
    $this->application->forceFill(['restricts_access' => true])->save();

    $response = authorizationRequest($this->user, $this->application);

    $response->assertForbidden()
        ->assertInertia(fn ($page) => $page
            ->component('oauth/AccessDenied')
            ->where('application.name', 'Reporting'));
});

test('a restricted application admits a user who has been granted access', function () {
    $this->application->forceFill(['restricts_access' => true])->save();
    $this->application->grantAccessTo($this->user);

    authorizationRequest($this->user, $this->application)
        ->assertRedirectContains(ENFORCEMENT_REDIRECT_URI);
});

test('revoking access turns the user away again', function () {
    $this->application->forceFill(['restricts_access' => true])->save();
    $grant = $this->application->grantAccessTo($this->user);

    authorizationRequest($this->user, $this->application)
        ->assertRedirectContains(ENFORCEMENT_REDIRECT_URI);

    $grant->revoke();

    authorizationRequest($this->user, $this->application)->assertForbidden();
});

test('access to one application does not admit a user to another', function () {
    $billing = Application::factory()->trusted()->create([
        'name' => 'Billing',
        'redirect_uris' => [ENFORCEMENT_REDIRECT_URI],
        'restricts_access' => true,
    ]);

    $this->application->forceFill(['restricts_access' => true])->save();
    $this->application->grantAccessTo($this->user);

    authorizationRequest($this->user, $this->application)
        ->assertRedirectContains(ENFORCEMENT_REDIRECT_URI);

    authorizationRequest($this->user, $billing)->assertForbidden();
});

test('a user granted access with no role is still admitted', function () {
    $this->application->forceFill(['restricts_access' => true])->save();
    $this->application->grantAccessTo($this->user, null);

    authorizationRequest($this->user, $this->application)
        ->assertRedirectContains(ENFORCEMENT_REDIRECT_URI);
});

test('a guest is sent to sign in rather than refused', function () {
    $this->application->forceFill(['restricts_access' => true])->save();

    authorizationRequest(null, $this->application)->assertRedirect(route('login'));
});

test('an approval needs a token that only an admitted request mints', function () {
    /*
     * The check runs where the client is named, which is the request that
     * starts the authorization. A user turned away there never reaches the
     * consent screen, so never obtains the token an approval requires.
     */
    $this->application->forceFill([
        'restricts_access' => true,
        'skips_authorization' => false,
    ])->save();

    authorizationRequest($this->user, $this->application)->assertForbidden();

    $this->actingAs($this->user)
        ->post('/oauth/authorize', ['auth_token' => 'invented'])
        ->assertForbidden();
});

test('an administrator turns the restriction on', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->put(route('applications.update', $this->application), [
        'name' => $this->application->name,
        'redirect_uris' => [ENFORCEMENT_REDIRECT_URI],
        'scopes' => ['openid'],
        'restricts_access' => true,
    ]);

    expect($this->application->refresh()->restricts_access)->toBeTrue();
});
