<?php

use App\Enums\ApplicationType;
use App\Enums\AuditEvent;
use App\Models\Application;
use App\Models\ApplicationRole;
use App\Models\AuditRecord;
use App\Models\User;
use App\Services\ApplicationManager;

/**
 * The trail is driven by domain events, so these exercise the real actions and
 * assert what was recorded, rather than calling the logger directly.
 */
function recorded(AuditEvent $event): ?AuditRecord
{
    return AuditRecord::where('event', $event->value)->first();
}

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create(['name' => 'Lauro Guedes']);
});

test('each action is recorded exactly once', function () {
    $this->actingAs($this->admin)->post(route('users.store'), [
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    /*
     * Guards against the listener being registered twice — once by event
     * discovery and once by hand — which would silently double every entry.
     */
    expect(AuditRecord::where('event', AuditEvent::UserCreated->value)->count())->toBe(1);
});

test('creating a user names the administrator who did it', function () {
    $this->actingAs($this->admin)->post(route('users.store'), [
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    $record = recorded(AuditEvent::UserCreated);

    expect($record)->not->toBeNull()
        ->and($record->causer->is($this->admin))->toBeTrue()
        ->and($record->subject->email)->toBe('alice@example.com')
        ->and($record->properties['email'])->toBe('alice@example.com');
});

test('the password is never written to the trail', function () {
    $this->actingAs($this->admin)->post(route('users.store'), [
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    expect(AuditRecord::all()->toJson())->not->toContain('correct-horse-battery-staple');
});

test('disabling a user is recorded as security activity', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->from(route('users.edit', $user))
        ->put(route('users.status.update', $user), ['enabled' => false]);

    expect(recorded(AuditEvent::UserDisabled)?->log_name)->toBe('security');
});

test('registering an application records it against that application', function () {
    $this->actingAs($this->admin)->post(route('applications.store'), [
        'name' => 'Reporting',
        'type' => 'confidential',
        'redirect_uris' => ['https://reports.example.com/auth/callback'],
    ]);

    $application = Application::where('name', 'Reporting')->sole();
    $record = recorded(AuditEvent::ApplicationCreated);

    expect($record->application_id)->toBe($application->id)
        ->and($record->properties['name'])->toBe('Reporting');
});

test('rotating a client secret is recorded without the secret', function () {
    $application = app(ApplicationManager::class)->create([
        'name' => 'Reporting',
        'type' => ApplicationType::Confidential,
        'redirect_uris' => ['https://reports.example.com/auth/callback'],
    ]);

    $this->actingAs($this->admin)->put(route('applications.secret.update', $application));

    $secret = session('clientSecret');
    $record = recorded(AuditEvent::ClientSecretRegenerated);

    expect($record)->not->toBeNull()
        ->and($record->application_id)->toBe($application->id)
        ->and(AuditRecord::all()->toJson())->not->toContain($secret);
});

test('granting access records the role given', function () {
    $application = Application::factory()->create();
    $role = ApplicationRole::factory()->create([
        'application_id' => $application->id,
        'name' => 'Analyst',
    ]);

    $application->grantAccessTo(User::factory()->create(), $role);

    $record = recorded(AuditEvent::AccessGranted);

    expect($record->application_id)->toBe($application->id)
        ->and($record->properties['role'])->toBe('Analyst');
});

test('a role change records both sides of it', function () {
    $application = Application::factory()->create();
    $analyst = ApplicationRole::factory()->create(['application_id' => $application->id, 'name' => 'Analyst']);
    $viewer = ApplicationRole::factory()->create(['application_id' => $application->id, 'name' => 'Viewer']);

    $grant = $application->grantAccessTo(User::factory()->create(), $analyst);

    $grant->assignRole($viewer);

    $record = recorded(AuditEvent::RoleChanged);

    expect($record->properties['from'])->toBe('Analyst')
        ->and($record->properties['to'])->toBe('Viewer');
});

describe('sign-in activity', function () {
    test('a successful sign-in is recorded against the user', function () {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $record = recorded(AuditEvent::UserLoggedIn);

        expect($record)->not->toBeNull()
            ->and($record->causer->is($user))->toBeTrue()
            ->and($record->log_name)->toBe('security');
    });

    test('a failed sign-in is recorded with the address tried and no causer', function () {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'not-the-password',
        ]);

        $record = recorded(AuditEvent::UserLoginFailed);

        expect($record)->not->toBeNull()
            ->and($record->causer)->toBeNull()
            ->and($record->properties['email'])->toBe($user->email);
    });

    test('the attempted password is never written to the trail', function () {
        $this->post(route('login.store'), [
            'email' => 'alice@example.com',
            'password' => 'attempted-secret-value',
        ]);

        expect(AuditRecord::all()->toJson())->not->toContain('attempted-secret-value');
    });

    test('signing out is recorded', function () {
        $this->actingAs(User::factory()->create())->post(route('logout'));

        expect(recorded(AuditEvent::UserLoggedOut))->not->toBeNull();
    });
});
