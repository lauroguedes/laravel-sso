<?php

use App\Models\User;

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
