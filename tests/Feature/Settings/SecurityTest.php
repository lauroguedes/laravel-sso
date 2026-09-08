<?php

use App\Models\User;
use App\Services\SessionManager;
use App\Services\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('security page is displayed', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
    Features::passkeys([
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('canManagePasskeys', true)
            ->where('passkeys', [])
            ->where('canManageTwoFactor', true)
            ->where('twoFactorEnabled', false),
        );
});

test('security page requires password confirmation when enabled', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->create();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(route('security.edit'));

    $response->assertRedirect(route('password.confirm'));
});

test('security page renders without two factor when feature is disabled', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    config(['fortify.features' => []]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('canManagePasskeys', false)
            ->where('passkeys', [])
            ->where('canManageTwoFactor', false)
            ->missing('twoFactorEnabled')
            ->missing('requiresConfirmation'),
        );
});

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('security.edit'));
});

describe('other sessions after a password change', function () {
    beforeEach(function () {
        /*
         * Sessions are only inspectable under the database driver, which is
         * what this server ships with; the suite runs on the array driver for
         * speed.
         */
        config()->set('session.driver', 'database');
    });

    /**
     * A session row belonging to someone, as the database driver stores it.
     */
    function plantSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Somewhere else',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
    }

    /**
     * Change a user's password through the interface.
     */
    function changePassword(User $user): TestResponse
    {
        return test()->actingAs($user)->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors();
    }

    test('are ended, because the old password may be why it changed', function () {
        $user = User::factory()->create();
        plantSession($user, 'elsewhere');

        changePassword($user);

        expect(DB::table('sessions')->where('id', 'elsewhere')->exists())->toBeFalse();
    });

    test('are left alone where an administrator turned that off', function () {
        app(Settings::class)->put(['logout_other_sessions_on_password_change' => false]);

        $user = User::factory()->create();
        plantSession($user, 'elsewhere');

        changePassword($user);

        expect(DB::table('sessions')->where('id', 'elsewhere')->exists())->toBeTrue();
    });

    test('belong to that person and nobody else', function () {
        $user = User::factory()->create();
        $bystander = User::factory()->create();
        plantSession($bystander, 'someone-else');

        changePassword($user);

        expect(DB::table('sessions')->where('id', 'someone-else')->exists())->toBeTrue();
    });

    test('the session doing the changing survives', function () {
        /*
         * Signing somebody out of the page they are on, in response to them
         * securing their own account, reads as the change having failed.
         */
        $user = User::factory()->create();
        plantSession($user, 'here');
        plantSession($user, 'elsewhere');

        app(SessionManager::class)->revokeOtherBrowserSessionsFor($user, 'here');

        expect(DB::table('sessions')->where('id', 'here')->exists())->toBeTrue()
            ->and(DB::table('sessions')->where('id', 'elsewhere')->exists())->toBeFalse();
    });
});
