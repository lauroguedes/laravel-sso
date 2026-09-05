<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The two streams an audit record can belong to.
 *
 * Kept apart so an operator investigating an incident can read authentication
 * activity without it being buried under routine administration, which is what
 * the activity log's indexed "log_name" column is for.
 */
enum AuditLog: string
{
    /** Something an administrator did to this server's configuration. */
    case Administration = 'administration';

    /** Something that happened to an account or a session. */
    case Security = 'security';

    /**
     * A short description for the administration interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Administration => 'Administration',
            self::Security => 'Security',
        };
    }
}
