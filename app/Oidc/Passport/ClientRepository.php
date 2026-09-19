<?php

declare(strict_types=1);

namespace App\Oidc\Passport;

use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository as PassportClientRepository;

/**
 * Passport's client lookup, refusing ids that cannot be client ids.
 *
 * Client ids are UUIDs, and every OAuth endpoint looks one up from whatever the
 * caller sent. On PostgreSQL a value that is not a UUID fails the query
 * itself, which would answer 500 where "no such client" belongs.
 */
class ClientRepository extends PassportClientRepository
{
    public function find(string|int $id): ?Client
    {
        return is_string($id) && ! Str::isUuid($id) ? null : parent::find($id);
    }
}
