<?php

use App\Enums\AuditEvent;
use App\Enums\AuditLog;
use App\Models\Application;
use App\Models\AuditRecord;
use App\Models\User;
use App\Services\AuditLogger;

test('a record captures who did what, to what, from where', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    app(AuditLogger::class)->record(AuditEvent::UserDisabled, $target, causer: $actor);

    $record = AuditRecord::sole();

    expect($record->event)->toBe('user.disabled')
        ->and($record->log_name)->toBe(AuditLog::Security->value)
        ->and($record->causer->is($actor))->toBeTrue()
        ->and($record->subject->is($target))->toBeTrue()
        ->and($record->created_at)->not->toBeNull();
});

test('administrative and security activity are separate streams', function () {
    $logger = app(AuditLogger::class);

    $logger->record(AuditEvent::ApplicationCreated);
    $logger->record(AuditEvent::UserLoggedIn);

    expect(AuditRecord::where('log_name', AuditLog::Administration->value)->count())->toBe(1)
        ->and(AuditRecord::where('log_name', AuditLog::Security->value)->count())->toBe(1);
});

test('a record can be attributed to no one', function () {
    app(AuditLogger::class)->record(AuditEvent::UserLoginFailed, properties: ['email' => 'nobody@example.com']);

    expect(AuditRecord::sole()->causer)->toBeNull();
});

test('an entry concerning an application can be found by it', function () {
    $application = Application::factory()->create();

    app(AuditLogger::class)->record(AuditEvent::ApplicationDisabled, $application, $application);

    expect(AuditRecord::forApplication($application)->count())->toBe(1);
});

test('audit history outlives the application it describes', function () {
    $application = Application::factory()->create();

    app(AuditLogger::class)->record(AuditEvent::ApplicationDisabled, $application, $application);

    $application->delete();

    /*
     * Removing an application must not erase the record of what was done to
     * it, which is why application_id carries no foreign key.
     */
    expect(AuditRecord::count())->toBe(1)
        ->and(AuditRecord::sole()->application_id)->toBe($application->id);
});

describe('sensitive values', function () {
    test('a credential is redacted however it is named', function (string $key) {
        app(AuditLogger::class)->record(AuditEvent::UserUpdated, properties: [$key => 'super-secret']);

        expect(AuditRecord::sole()->properties[$key])->toBe('[redacted]');
    })->with([
        'password', 'client_secret', 'secret', 'plainSecret',
        'access_token', 'refresh_token', 'id_token', 'code_verifier',
        'two_factor_secret', 'remember_token',
    ]);

    test('a credential nested inside metadata is redacted too', function () {
        app(AuditLogger::class)->record(AuditEvent::ApplicationUpdated, properties: [
            'changes' => ['name' => 'Reporting', 'secret' => 'super-secret'],
        ]);

        $properties = AuditRecord::sole()->properties;

        expect($properties['changes']['secret'])->toBe('[redacted]')
            ->and($properties['changes']['name'])->toBe('Reporting');
    });

    test('ordinary metadata is kept', function () {
        app(AuditLogger::class)->record(AuditEvent::UserUpdated, properties: ['email' => 'alice@example.com']);

        expect(AuditRecord::sole()->properties['email'])->toBe('alice@example.com');
    });
});
