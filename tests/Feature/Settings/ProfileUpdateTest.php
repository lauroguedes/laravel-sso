<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('an address must be lowercase', function () {
    /*
     * The rule is shared by every path that accepts an address, because this
     * server's subject identity is the address and "unique" is case sensitive
     * on PostgreSQL: two accounts differing only in case would each receive
     * tokens as a different person.
     */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'Test@Example.com',
        ])
        ->assertSessionHasErrors('email');

    expect($user->refresh()->email)->not->toBe('Test@Example.com');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('an account cannot be deleted through the interface', function () {
    /*
     * Accounts are disabled, never deleted, so that the audit trail keeps
     * naming a real person. A self-service delete would leave their entries
     * with no causer and drop their access grants without an administrator
     * ever seeing it.
     */
    expect(Route::has('profile.destroy'))->toBeFalse();

    $settingsRoutes = collect(app('router')->getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->uri(), 'settings/profile'))
        ->flatMap(fn ($route): array => $route->methods())
        ->unique()
        ->values();

    expect($settingsRoutes->all())->toEqualCanonicalizing(['GET', 'HEAD', 'PATCH']);
});
