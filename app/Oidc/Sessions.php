<?php

declare(strict_types=1);

namespace App\Oidc;

use App\Models\Application;
use App\Oidc\Contracts\DiscoversProvider;
use App\Oidc\Contracts\EndsSessions;
use App\Oidc\Contracts\LogoutRequest;
use App\Oidc\Contracts\ProvidesSigningKeys;
use App\Oidc\Exceptions\SigningKeyUnavailable;
use Illuminate\Http\Request;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Exception as JwtException;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;

/**
 * Checks what an RP-initiated logout request may do.
 *
 * An id_token_hint counts only when this server signed it; its expiry does not
 * matter, as the specification asks. The client is the one it was issued to,
 * or the client_id sent instead, and the two must agree when both are sent.
 * The browser only goes where that client registered as a post-logout
 * destination, matched exactly, and never to its redirect URIs, which receive
 * authorization codes.
 */
class Sessions implements EndsSessions
{
    public function __construct(
        private readonly ProvidesSigningKeys $keys,
        private readonly DiscoversProvider $discovery,
        private readonly ClientRepository $clients,
    ) {}

    public function inspect(Request $request): LogoutRequest
    {
        $hint = $this->verifiedHint($request->input('id_token_hint'));
        $client = $this->client($hint, $request->input('client_id'));

        return new LogoutRequest(
            client: $client,
            destination: $this->destination($client, $request),
            subject: $hint?->claims()->get('sub'),
        );
    }

    /**
     * The hint, when this server signed it.
     */
    private function verifiedHint(mixed $hint): ?UnencryptedToken
    {
        $issuer = $this->discovery->issuer();

        if (! is_string($hint) || $hint === '' || $issuer === '') {
            return null;
        }

        try {
            $token = (new Parser(new JoseEncoder))->parse($hint);
            $signedWith = new SignedWith(new Sha256, InMemory::plainText($this->keys->publicKey()));
        } catch (JwtException|SigningKeyUnavailable) {
            return null;
        }

        return $token instanceof UnencryptedToken && (new Validator)->validate($token, $signedWith, new IssuedBy($issuer))
            ? $token
            : null;
    }

    /**
     * The application the request names: the hint's audience, or client_id.
     */
    private function client(?UnencryptedToken $hint, mixed $clientId): ?Client
    {
        $audience = $hint?->claims()->get('aud')[0] ?? null;
        $clientId = is_string($clientId) && $clientId !== '' ? $clientId : null;

        if ($audience !== null && $clientId !== null && $audience !== $clientId) {
            return null;
        }

        $id = $audience ?? $clientId;

        return $id === null ? null : $this->clients->findActive($id);
    }

    /**
     * Where the browser goes afterwards, when the client registered it.
     */
    private function destination(?Client $client, Request $request): ?string
    {
        $uri = $request->input('post_logout_redirect_uri');

        if (! $client instanceof Application || ! is_string($uri) || ! $client->permitsLogoutRedirect($uri)) {
            return null;
        }

        $state = $request->input('state');

        return is_string($state) && $state !== ''
            ? $uri.(str_contains($uri, '?') ? '&' : '?').http_build_query(['state' => $state])
            : $uri;
    }
}
