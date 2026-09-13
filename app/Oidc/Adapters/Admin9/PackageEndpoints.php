<?php

declare(strict_types=1);

namespace App\Oidc\Adapters\Admin9;

use Admin9\OidcServer\Http\Controllers\OidcController;
use Illuminate\Http\Request;
use Laravel\Passport\Client;

/**
 * The package's controller, with the protected steps its endpoints are built
 * from made public.
 *
 * The adapter needs client authentication and token lookup without the request
 * handling wrapped around them. The package's own names are kept, so each call
 * traces straight back to the vendor code.
 */
class PackageEndpoints extends OidcController
{
    public function authenticateClient(Request $request): ?Client
    {
        return parent::authenticateClient($request);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findToken(string $token, string $tokenTypeHint): ?array
    {
        return parent::findToken($token, $tokenTypeHint);
    }

    public function revokeToken(string $token, string $tokenTypeHint, Client $client): void
    {
        parent::revokeToken($token, $tokenTypeHint, $client);
    }
}
