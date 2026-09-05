<?php

use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('a disabled user cannot sign in', function () {
    $user = User::factory()->disabled()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('the sign-in failure does not reveal that the account is disabled', function () {
    /*
     * A distinct message would let anyone with a login form enumerate which
     * addresses belong to disabled accounts, so both failures are generic.
     */
    $disabled = User::factory()->disabled()->create();

    $this->post(route('login.store'), [
        'email' => $disabled->email,
        'password' => 'password',
    ])->assertSessionHasErrors(['email' => trans('auth.failed')]);

    $this->flushSession();

    $enabled = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $enabled->email,
        'password' => 'not-the-password',
    ])->assertSessionHasErrors(['email' => trans('auth.failed')]);
});

test('a disabled user with two factor enabled is refused before the challenge', function () {
    $user = User::factory()->disabled()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionMissing('login.id');
});

test('disabling a signed-in user ends their session on the next request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->disable();

    $response = $this->get(route('dashboard'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});

test('disabling a user revokes the tokens applications already hold', function () {
    /*
     * Stopping somebody signing in is not enough on its own: an application
     * holding a live access token could keep calling resource servers on their
     * behalf until it expired, and keep renewing it with its refresh token.
     * Applications cascade the same way when they are disabled.
     */
    $user = User::factory()->create();
    $application = Application::factory()->create();

    $token = $application->tokens()->create([
        'id' => 'token-under-test',
        'user_id' => $user->id,
        'scopes' => ['openid'],
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);

    $user->disable();

    expect($token->refresh()->revoked)->toBeTrue();
});

test('disabling a user ends the browser sessions of that user only', function () {
    config()->set('session.driver', 'database');

    $user = User::factory()->create();
    $other = User::factory()->create();

    foreach ([[$user, 'target-session'], [$other, 'other-session']] as [$owner, $id]) {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $owner->id,
            'ip_address' => '198.51.100.7',
            'user_agent' => 'Mozilla/5.0',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);
    }

    $user->disable();

    expect(DB::table('sessions')->where('id', 'target-session')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'other-session')->exists())->toBeTrue();
});

test('re-enabling a user does not restore revoked tokens', function () {
    $user = User::factory()->create();
    $application = Application::factory()->create();

    $token = $application->tokens()->create([
        'id' => 'token-under-test',
        'user_id' => $user->id,
        'scopes' => ['openid'],
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);

    $user->disable();
    $user->enable();

    expect($token->refresh()->revoked)->toBeTrue();
});

test('a re-enabled user can sign in again', function () {
    $user = User::factory()->disabled()->create();

    $user->enable();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
});

test('signing in records the moment it happened', function () {
    $user = User::factory()->create(['last_login_at' => null]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();

    expect($user->refresh()->last_login_at)->not->toBeNull();
});
