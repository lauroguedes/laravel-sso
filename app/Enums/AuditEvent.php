<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every event this server records.
 *
 * The values are the stable identifiers an operator greps for and an external
 * log pipeline matches on, so they are treated as a contract: rename one and
 * existing history stops matching.
 */
enum AuditEvent: string
{
    case UserLoggedIn = 'user.login';
    case UserLoginFailed = 'user.login.failed';
    case UserLoggedOut = 'user.logout';
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserDisabled = 'user.disabled';
    case UserEnabled = 'user.enabled';

    case ApplicationCreated = 'application.created';
    case ApplicationUpdated = 'application.updated';
    case ApplicationDisabled = 'application.disabled';
    case ApplicationEnabled = 'application.enabled';
    case ClientSecretRegenerated = 'client.secret.regenerated';

    case AccessGranted = 'application.access.granted';
    case AccessRevoked = 'application.access.revoked';
    case RoleChanged = 'application.role.changed';

    case SessionRevoked = 'session.revoked';

    /**
     * The stream this event belongs to.
     */
    public function log(): AuditLog
    {
        return match ($this) {
            self::UserLoggedIn,
            self::UserLoginFailed,
            self::UserLoggedOut,
            self::UserDisabled,
            self::SessionRevoked => AuditLog::Security,
            default => AuditLog::Administration,
        };
    }

    /**
     * A short description for the administration interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::UserLoggedIn => 'Signed in',
            self::UserLoginFailed => 'Sign-in failed',
            self::UserLoggedOut => 'Signed out',
            self::UserCreated => 'User created',
            self::UserUpdated => 'User updated',
            self::UserDisabled => 'User disabled',
            self::UserEnabled => 'User enabled',
            self::ApplicationCreated => 'Application registered',
            self::ApplicationUpdated => 'Application updated',
            self::ApplicationDisabled => 'Application disabled',
            self::ApplicationEnabled => 'Application enabled',
            self::ClientSecretRegenerated => 'Client secret regenerated',
            self::AccessGranted => 'Access granted',
            self::AccessRevoked => 'Access revoked',
            self::RoleChanged => 'Role changed',
            self::SessionRevoked => 'Session revoked',
        };
    }
}
