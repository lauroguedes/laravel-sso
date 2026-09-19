<?php

declare(strict_types=1);

namespace App\Oidc;

use App\Oidc\Contracts\AuthenticatesClients;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Request;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

/**
 * Authenticates a client at the introspection and revocation endpoints.
 *
 * A confidential client proves itself with its secret, over HTTP Basic or in
 * the body. A public client has no secret, so it is known by its id alone, and
 * each endpoint decides what that is enough for. A disabled application never
 * authenticates.
 */
class ClientAuthentication implements AuthenticatesClients
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly Hasher $hasher,
    ) {}

    public function authenticate(Request $request): ?Client
    {
        [$clientId, $secret] = $this->credentials($request);

        $client = is_string($clientId) ? $this->clients->findActive($clientId) : null;

        if ($client === null) {
            return null;
        }

        if (! $client->confidential()) {
            return $secret === null ? $client : null;
        }

        return is_string($secret) && $this->hasher->check($secret, $client->getAttribute('secret')) ? $client : null;
    }

    /**
     * The client id and secret, from HTTP Basic when present, else the body.
     *
     * Basic credentials are form encoded before they are joined, as RFC 6749
     * section 2.3.1 requires, so they are decoded here.
     *
     * @return array{0: mixed, 1: mixed}
     */
    private function credentials(Request $request): array
    {
        $user = $request->getUser();

        if ($user !== null) {
            return [urldecode($user), urldecode((string) $request->getPassword())];
        }

        return [$request->input('client_id'), $request->input('client_secret')];
    }
}
