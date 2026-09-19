<?php

use App\Models\User;
use Laravel\Fortify\Features;

/**
 * Turn the deployment switch off the way an operator does: by removing the
 * feature Fortify registers routes for.
 */
function withoutEmailVerification(): void
{
    config()->set('fortify.features', array_values(array_diff(
        config('fortify.features'),
        [Features::emailVerification()],
    )));
}

/**
 * Verification is only meaningful if something is actually withheld until the
 * address is confirmed. These cover the enforcement, not the confirmation
 * flow itself, which EmailVerificationTest already exercises.
 */
test('an unverified user is sent to the verification prompt', function () {
    $response = $this->actingAs(User::factory()->unverified()->create())
        ->get(route('dashboard'));

    $response->assertRedirect(route('verification.notice'));
});

test('a verified user reaches the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk();
});

test('an unverified user cannot reach the administration pages', function () {
    $admin = User::factory()->superAdmin()->unverified()->create();

    $this->actingAs($admin)->get(route('users.index'))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs($admin)->get(route('applications.index'))
        ->assertRedirect(route('verification.notice'));
});

test('confirming the address restores access', function () {
    $user = User::factory()->unverified()->create();

    $user->markEmailAsVerified();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

test('an unverified user is not withheld when verification is turned off', function () {
    withoutEmailVerification();

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('dashboard'))
        ->assertOk();
});

test('the model reports the address truthfully whatever the switch says', function () {
    withoutEmailVerification();

    $user = User::factory()->unverified()->create();

    /*
     * The user is admitted because this server never asked them to confirm,
     * but nothing may claim the address was verified: the middleware decides
     * enforcement, the model reports fact.
     */
    expect($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->resolveOidcClaim('email_verified'))->toBeFalse();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

test('the profile page offers to resend only when verification is asked for', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('mustVerifyEmail', true));

    withoutEmailVerification();

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('mustVerifyEmail', false));
});
