<?php

declare(strict_types=1);

namespace App\Oidc\Contracts;

use Illuminate\Http\Request;
use Laravel\Passport\Client;

/**
 * Works out which client a protocol request comes from.
 */
interface AuthenticatesClients
{
    /**
     * The client the request authenticates as, or null when it does not.
     *
     * Credentials are read from HTTP Basic authentication, or from
     * "client_id" and "client_secret" in the body.
     */
    public function authenticate(Request $request): ?Client;
}
