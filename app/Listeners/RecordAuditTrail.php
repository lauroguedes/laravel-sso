<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AuditEvent;
use App\Events\ApplicationCreated;
use App\Events\ApplicationDisabled;
use App\Events\ApplicationEnabled;
use App\Events\ApplicationUpdated;
use App\Events\ClientSecretRegenerated;
use App\Events\SessionRevoked;
use App\Events\UserApplicationAccessGranted;
use App\Events\UserApplicationAccessRevoked;
use App\Events\UserApplicationRoleChanged;
use App\Events\UserCreated;
use App\Events\UserDisabled;
use App\Events\UserEnabled;
use App\Events\UserUpdated;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Turns domain and framework events into audit records.
 *
 * Listening keeps the trail in one place rather than scattered through the
 * controllers, which is what makes it trustworthy: an action performed by a
 * console command or a listener is recorded exactly as one performed through
 * the interface, because both raise the same event.
 *
 * Each method is registered by Laravel's event discovery, which matches any
 * "handle*" method whose first parameter is typed. Registering this as a
 * subscriber as well would record everything twice.
 */
class RecordAuditTrail
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handleUserCreated(UserCreated $event): void
    {
        $this->audit->record(AuditEvent::UserCreated, $event->user, properties: [
            'email' => $event->user->email,
        ]);
    }

    public function handleUserUpdated(UserUpdated $event): void
    {
        $this->audit->record(AuditEvent::UserUpdated, $event->user, properties: [
            'email' => $event->user->email,
        ]);
    }

    public function handleUserDisabled(UserDisabled $event): void
    {
        $this->audit->record(AuditEvent::UserDisabled, $event->user);
    }

    public function handleUserEnabled(UserEnabled $event): void
    {
        $this->audit->record(AuditEvent::UserEnabled, $event->user);
    }

    public function handleApplicationCreated(ApplicationCreated $event): void
    {
        $this->audit->record(
            AuditEvent::ApplicationCreated,
            $event->application,
            $event->application,
            ['name' => $event->application->name],
        );
    }

    public function handleApplicationUpdated(ApplicationUpdated $event): void
    {
        $this->audit->record(
            AuditEvent::ApplicationUpdated,
            $event->application,
            $event->application,
            ['name' => $event->application->name],
        );
    }

    public function handleApplicationDisabled(ApplicationDisabled $event): void
    {
        $this->audit->record(AuditEvent::ApplicationDisabled, $event->application, $event->application);
    }

    public function handleApplicationEnabled(ApplicationEnabled $event): void
    {
        $this->audit->record(AuditEvent::ApplicationEnabled, $event->application, $event->application);
    }

    public function handleSecretRegenerated(ClientSecretRegenerated $event): void
    {
        /*
         * The new secret is deliberately absent: the event does not carry it,
         * and AuditLogger would redact it if it did.
         */
        $this->audit->record(
            AuditEvent::ClientSecretRegenerated,
            $event->application,
            $event->application,
        );
    }

    public function handleAccessGranted(UserApplicationAccessGranted $event): void
    {
        $this->audit->record(
            AuditEvent::AccessGranted,
            $event->grant->user,
            $event->grant->application,
            ['role' => $event->grant->role?->name],
        );
    }

    public function handleAccessRevoked(UserApplicationAccessRevoked $event): void
    {
        $this->audit->record(
            AuditEvent::AccessRevoked,
            $event->grant->user,
            $event->grant->application,
        );
    }

    public function handleRoleChanged(UserApplicationRoleChanged $event): void
    {
        $this->audit->record(
            AuditEvent::RoleChanged,
            $event->grant->user,
            $event->grant->application,
            ['from' => $event->from?->name, 'to' => $event->grant->role?->name],
        );
    }

    public function handleLogin(Login $event): void
    {
        $user = $this->userFrom($event->user);

        $this->audit->record(AuditEvent::UserLoggedIn, $user, causer: $user);
    }

    public function handleLogout(Logout $event): void
    {
        $user = $this->userFrom($event->user);

        $this->audit->record(AuditEvent::UserLoggedOut, $user, causer: $user);
    }

    public function handleSessionRevoked(SessionRevoked $event): void
    {
        $this->audit->record(
            AuditEvent::SessionRevoked,
            $event->user,
            $event->application,
            ['kind' => $event->kind, ...$event->context],
        );
    }

    /**
     * Record a failed sign-in.
     *
     * Nobody is signed in, so the record has no causer. The address that was
     * tried is kept — that is the point of the record — but never the
     * credential, which AuditLogger would redact anyway.
     */
    public function handleLoginFailed(Failed $event): void
    {
        $this->audit->record(
            AuditEvent::UserLoginFailed,
            $this->userFrom($event->user),
            properties: ['email' => $event->credentials['email'] ?? null],
            causer: null,
        );
    }

    /**
     * The user behind a framework authentication event.
     *
     * Those events carry an Authenticatable, which need not be this
     * application's user model — a failed sign-in carries none at all.
     */
    private function userFrom(?Authenticatable $user): ?User
    {
        return $user instanceof User ? $user : null;
    }
}
