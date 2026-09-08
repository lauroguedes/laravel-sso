<?php

use App\Enums\AuditEvent;
use App\Enums\PlatformPermission;
use App\Models\Application;
use App\Models\AuditRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

/**
 * Put a browser session in the table, as the database session driver does.
 */
function seedSession(User $user, string $id = 'session-under-test'): string
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '198.51.100.7',
        'user_agent' => 'Mozilla/5.0',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->timestamp,
    ]);

    return $id;
}

/**
 * Issue an access token to an application on a user's behalf.
 */
function seedToken(User $user, Application $application, string $id = 'token-under-test')
{
    return $application->tokens()->create([
        'id' => $id,
        'user_id' => $user->id,
        'scopes' => ['openid'],
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);
}

beforeEach(function () {
    /*
     * The test environment stores sessions in an array for speed; the
     * application's own default is the database, which is what makes them
     * inspectable at all.
     */
    config()->set('session.driver', 'database');

    $this->admin = User::factory()->superAdmin()->create();
    $this->user = User::factory()->create(['name' => 'Alice Smith']);
    $this->application = Application::factory()->create(['name' => 'Reporting']);
});

test('the page lists browser sessions and issued tokens', function () {
    seedSession($this->user);
    seedToken($this->user, $this->application);

    $response = $this->actingAs($this->admin)->get(route('sessions.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('sessions/Index')
        ->where('tracksSessions', true)
        ->has('browserSessions', 1)
        ->where('browserSessions.0.user.name', 'Alice Smith')
        ->where('browserSessions.0.ip_address', '198.51.100.7')
        ->has('tokens', 1)
        ->where('tokens.0.application', 'Reporting'));
});

test('a revoked token is not listed', function () {
    seedToken($this->user, $this->application)->forceFill(['revoked' => true])->save();

    $this->actingAs($this->admin)->get(route('sessions.index'))
        ->assertInertia(fn ($page) => $page->has('tokens', 0));
});

test('an administrator ends one browser session', function () {
    $id = seedSession($this->user);

    $this->actingAs($this->admin)
        ->from(route('sessions.index'))
        ->delete(route('sessions.destroy', $id));

    expect(DB::table('sessions')->where('id', $id)->exists())->toBeFalse();
});

test('ending a session is recorded', function () {
    $this->actingAs($this->admin)
        ->from(route('sessions.index'))
        ->delete(route('sessions.destroy', seedSession($this->user)));

    expect(AuditRecord::where('event', AuditEvent::SessionRevoked->value)->count())->toBe(1);
});

test('an administrator revokes one token', function () {
    $token = seedToken($this->user, $this->application);

    $this->actingAs($this->admin)
        ->from(route('sessions.index'))
        ->delete(route('tokens.destroy', $token->id));

    expect($token->refresh()->revoked)->toBeTrue();
});

test('signing a user out everywhere ends sessions and revokes tokens', function () {
    seedSession($this->user);
    $token = seedToken($this->user, $this->application);

    $this->actingAs($this->admin)
        ->from(route('users.edit', $this->user))
        ->delete(route('users.sessions.destroy', $this->user));

    expect(DB::table('sessions')->where('user_id', $this->user->id)->exists())->toBeFalse()
        ->and($token->refresh()->revoked)->toBeTrue();
});

test('signing one user out leaves another alone', function () {
    $other = User::factory()->create();

    seedSession($this->user, 'alice-session');
    seedSession($other, 'other-session');
    $otherToken = seedToken($other, $this->application, 'other-token');

    $this->actingAs($this->admin)
        ->from(route('users.edit', $this->user))
        ->delete(route('users.sessions.destroy', $this->user));

    expect(DB::table('sessions')->where('id', 'other-session')->exists())->toBeTrue()
        ->and($otherToken->refresh()->revoked)->toBeFalse();
});

test('revoking an application tokens leaves other applications alone', function () {
    $billing = Application::factory()->create(['name' => 'Billing']);

    $reportingToken = seedToken($this->user, $this->application, 'reporting-token');
    $billingToken = seedToken($this->user, $billing, 'billing-token');

    $this->actingAs($this->admin)
        ->from(route('applications.show', $this->application))
        ->delete(route('applications.tokens.destroy', $this->application));

    expect($reportingToken->refresh()->revoked)->toBeTrue()
        ->and($billingToken->refresh()->revoked)->toBeFalse();
});

test('revoking a token also revokes its refresh token', function () {
    $token = seedToken($this->user, $this->application);

    $refresh = Passport::refreshToken()->newQuery()->create([
        'id' => 'refresh-under-test',
        'access_token_id' => $token->id,
        'revoked' => false,
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($this->admin)
        ->from(route('sessions.index'))
        ->delete(route('tokens.destroy', $token->id));

    expect($refresh->refresh()->revoked)->toBeTrue();
});

test('the page says so when sessions are not stored in the database', function () {
    config()->set('session.driver', 'array');

    $this->actingAs($this->admin)->get(route('sessions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('tracksSessions', false)
            ->has('browserSessions', 0));
});

describe('the controls are shown only to whoever may use them', function () {
    test('the user page offers to sign someone out everywhere', function () {
        $this->actingAs($this->admin)
            ->get(route('users.edit', $this->user))
            ->assertInertia(fn ($page) => $page->where('canRevokeSessions', true));
    });

    test('a viewer is not offered it', function () {
        $viewer = User::factory()->create();
        Permission::findOrCreate(PlatformPermission::UsersView->value, 'web');
        $viewer->givePermissionTo(PlatformPermission::UsersView->value);

        $this->actingAs($viewer)
            ->get(route('users.edit', $this->user))
            ->assertInertia(fn ($page) => $page->where('canRevokeSessions', false));
    });

    test('the application page offers to revoke its tokens', function () {
        /*
         * Revoking an application's tokens is gated by the same ability that
         * edits it, so the existing "canManage" prop is what the control
         * reads; asserting it here pins that they stay the same ability.
         */
        $this->actingAs($this->admin)
            ->get(route('applications.show', $this->application))
            ->assertInertia(fn ($page) => $page->where('canManage', true));
    });

    test('an application viewer is not offered it', function () {
        $viewer = User::factory()->create();
        Permission::findOrCreate(PlatformPermission::ApplicationsView->value, 'web');
        $viewer->givePermissionTo(PlatformPermission::ApplicationsView->value);

        $this->actingAs($viewer)
            ->get(route('applications.show', $this->application))
            ->assertInertia(fn ($page) => $page->where('canManage', false));
    });
});

describe('permission isolation', function () {
    test('view permission alone does not allow ending a session', function () {
        $viewer = User::factory()->create();
        Permission::findOrCreate(PlatformPermission::UsersView->value, 'web');
        $viewer->givePermissionTo(PlatformPermission::UsersView->value);

        $this->actingAs($viewer)->get(route('sessions.index'))->assertOk();

        $this->actingAs($viewer)
            ->delete(route('sessions.destroy', seedSession($this->user)))
            ->assertForbidden();
    });

    test('view permission alone does not allow signing a user out everywhere', function () {
        $viewer = User::factory()->create();
        Permission::findOrCreate(PlatformPermission::UsersView->value, 'web');
        $viewer->givePermissionTo(PlatformPermission::UsersView->value);

        seedSession($this->user);

        $this->actingAs($viewer)
            ->delete(route('users.sessions.destroy', $this->user))
            ->assertForbidden();

        expect(DB::table('sessions')->where('user_id', $this->user->id)->exists())->toBeTrue();
    });

    test('view permission alone does not allow revoking an application tokens', function () {
        $viewer = User::factory()->create();
        Permission::findOrCreate(PlatformPermission::ApplicationsView->value, 'web');
        $viewer->givePermissionTo(PlatformPermission::ApplicationsView->value);

        $token = seedToken($this->user, $this->application);

        $this->actingAs($viewer)
            ->delete(route('applications.tokens.destroy', $this->application))
            ->assertForbidden();

        expect($token->refresh()->revoked)->toBeFalse();
    });

    test('a user without any platform permission cannot see sessions', function () {
        assertPageRefused(
            $this->actingAs(User::factory()->create())->get(route('sessions.index'))
        );
    });
});
