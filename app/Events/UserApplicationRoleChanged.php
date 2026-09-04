<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ApplicationRole;
use App\Models\ApplicationUser;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when the role a user holds in an application changes.
 *
 * Granting and revoking access alone would leave a gap in the audit trail:
 * a user's privileges inside an application can change without their access
 * starting or ending.
 */
class UserApplicationRoleChanged
{
    use Dispatchable;

    public function __construct(
        public readonly ApplicationUser $grant,
        public readonly ?ApplicationRole $from,
    ) {}
}
