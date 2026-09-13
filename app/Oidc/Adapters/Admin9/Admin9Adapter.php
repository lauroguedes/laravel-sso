<?php

declare(strict_types=1);

namespace App\Oidc\Adapters\Admin9;

use App\Oidc\Contracts\AuthenticatesClients;
use App\Oidc\Contracts\DiscoversProvider;
use App\Oidc\Contracts\EndsSessions;
use App\Oidc\Contracts\IntrospectsTokens;
use App\Oidc\Contracts\OidcAdapter;
use App\Oidc\Contracts\ProvidesSigningKeys;
use App\Oidc\Contracts\RevokesTokens;
use App\Oidc\Exceptions\OAuthError;
use App\Oidc\Exceptions\SigningKeyUnavailable;
use Illuminate\Http\Request;
use Laravel\Passport\Client;
use Symfony\Component\HttpFoundation\Response;

/**
 * admin9/laravel-oidc-server behind this project's ports.
 *
 * A thin wrapper: every answer comes from the package unchanged. It lets the
 * native adapter lend a port from the package until that port is rebuilt, and
 * it is removed together with the package.
 */
class Admin9Adapter implements AuthenticatesClients, DiscoversProvider, EndsSessions, IntrospectsTokens, OidcAdapter, ProvidesSigningKeys, RevokesTokens
{
    public function __construct(private readonly PackageEndpoints $endpoints) {}

    public function discovery(): DiscoversProvider
    {
        return $this;
    }

    public function signingKeys(): ProvidesSigningKeys
    {
        return $this;
    }

    public function clients(): AuthenticatesClients
    {
        return $this;
    }

    public function introspection(): IntrospectsTokens
    {
        return $this;
    }

    public function revocation(): RevokesTokens
    {
        return $this;
    }

    public function sessions(): EndsSessions
    {
        return $this;
    }

    public function issuer(): string
    {
        return (string) $this->metadata()['issuer'];
    }

    public function metadata(): array
    {
        return $this->endpoints->discovery()->getData(true);
    }

    public function privateKey(): string
    {
        return $this->keyFile('private');
    }

    public function publicKey(): string
    {
        return $this->keyFile('public');
    }

    public function keyId(): string
    {
        return $this->endpoints->generateKeyId($this->publicKey());
    }

    public function keySet(): array
    {
        $response = $this->endpoints->jwks();
        $body = $response->getData(true);

        if (! $response->isOk()) {
            throw new SigningKeyUnavailable($body['error'], $body['error_description']);
        }

        return $body;
    }

    public function authenticate(Request $request): ?Client
    {
        return $this->endpoints->authenticateClient($request);
    }

    public function introspect(Client $client, ?string $token, ?string $tokenTypeHint): array
    {
        $tokenTypeHint = $this->tokenTypeHint($tokenTypeHint);

        if ($token === null) {
            return ['active' => false];
        }

        return $this->endpoints->findToken($token, $tokenTypeHint) ?? ['active' => false];
    }

    public function revoke(Client $client, ?string $token, ?string $tokenTypeHint): void
    {
        $tokenTypeHint = $this->tokenTypeHint($tokenTypeHint);

        if ($token !== null) {
            $this->endpoints->revokeToken($token, $tokenTypeHint, $client);
        }
    }

    public function endSession(Request $request): Response
    {
        return $this->endpoints->logout($request);
    }

    /**
     * One half of the key pair, from the file the package always reads.
     *
     * @return non-empty-string
     */
    private function keyFile(string $type): string
    {
        $path = storage_path("oauth-{$type}.key");
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false || $contents === '') {
            throw new SigningKeyUnavailable(ucfirst($type).' key not found', "The OAuth {$type} key has not been generated.");
        }

        return $contents;
    }

    /**
     * The token type the package looks a token up as.
     *
     * No hint means an access token, and the package refuses any hint it does
     * not know.
     */
    private function tokenTypeHint(?string $hint): string
    {
        $hint ??= 'access_token';

        if (! in_array($hint, ['access_token', 'refresh_token'], true)) {
            throw OAuthError::unsupportedTokenType();
        }

        return $hint;
    }
}
