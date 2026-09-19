<?php

declare(strict_types=1);

namespace App\Oidc;

use App\Models\User;
use App\Oidc\Contracts\DiscoversProvider;
use App\Oidc\Contracts\IntrospectsTokens;
use App\Oidc\Contracts\ResolvesClaims;
use App\Oidc\Contracts\RevokesTokens;
use App\Services\SessionManager;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use JsonException;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use League\OAuth2\Server\CryptTrait;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use LogicException;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

/**
 * Introspection and revocation, over one way of proving a presented token.
 *
 * An access token counts only once Passport's resource server has verified its
 * signature, expiry and revocation, exactly as for an API request. A refresh
 * token counts only once it decrypts with Passport's key. A bare token id
 * proves nothing, so it is never looked up.
 *
 * A type hint only decides which kind is tried first: an unknown hint is
 * ignored, and a token of the other kind is still found (RFC 7009 section 2.1,
 * RFC 7662 section 2.1).
 */
class TokenState implements IntrospectsTokens, RevokesTokens
{
    use CryptTrait;

    public function __construct(
        private readonly ResourceServer $resourceServer,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly ResolvesClaims $claims,
        private readonly SessionManager $sessions,
        private readonly DiscoversProvider $discovery,
        Encrypter $encrypter,
    ) {
        $this->setEncryptionKey(Passport::tokenEncryptionKey($encrypter));
    }

    public function introspect(Client $client, ?string $token, ?string $tokenTypeHint): array
    {
        $presented = $this->find($token, $tokenTypeHint);

        return $presented === null ? ['active' => false] : $this->describe($presented);
    }

    /**
     * Revocation only ever acts on the asking client's own tokens, and
     * revoking either token of a pair revokes both.
     *
     * A refresh token is also revoked by its own id, because it can outlive the
     * record of its access token, which passport:purge removes once that token
     * has long expired.
     */
    public function revoke(Client $client, ?string $token, ?string $tokenTypeHint): void
    {
        $presented = $this->find($token, $tokenTypeHint);

        if ($presented === null || $presented->clientId !== $client->getKey()) {
            return;
        }

        $this->sessions->revokeTokensWhere(fn (Builder $tokens) => $tokens->whereKey($presented->accessTokenId));

        if ($presented->refreshTokenId !== null) {
            $this->refreshTokens->revokeRefreshToken($presented->refreshTokenId);
        }
    }

    /**
     * The presented token, tried as the hinted kind first.
     */
    private function find(?string $token, ?string $tokenTypeHint): ?PresentedToken
    {
        if ($token === null || $token === '') {
            return null;
        }

        return $tokenTypeHint === 'refresh_token'
            ? $this->refreshToken($token) ?? $this->accessToken($token)
            : $this->accessToken($token) ?? $this->refreshToken($token);
    }

    /**
     * An access token, once the resource server accepts it.
     *
     * Who it belongs to comes from the attributes the resource server sets.
     * Those leave out its lifetime, which is read from the token's claims.
     *
     * @param  non-empty-string  $token
     */
    private function accessToken(string $token): ?PresentedToken
    {
        try {
            $validated = $this->resourceServer->validateAuthenticatedRequest(
                (new PsrHttpFactory)->createRequest(Request::create('/', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token])),
            );
        } catch (OAuthServerException) {
            return null;
        }

        $jwt = (new Parser(new JoseEncoder))->parse($token);

        if (! $jwt instanceof UnencryptedToken) {
            return null;
        }

        return new PresentedToken(
            type: 'access_token',
            accessTokenId: $validated->getAttribute('oauth_access_token_id'),
            clientId: $validated->getAttribute('oauth_client_id'),
            userId: $validated->getAttribute('oauth_user_id') ?: null,
            scopes: $validated->getAttribute('oauth_scopes', []),
            expiresAt: $jwt->claims()->get('exp')->getTimestamp(),
            issuedAt: $jwt->claims()->get('iat')?->getTimestamp(),
        );
    }

    /**
     * A refresh token, once it decrypts and its record is still live.
     *
     * Authorization codes are encrypted with the same key, so a payload counts
     * only when it carries a refresh token's fields.
     */
    private function refreshToken(string $token): ?PresentedToken
    {
        try {
            $payload = json_decode($this->decrypt($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (LogicException|JsonException) {
            return null;
        }

        if (! isset($payload['refresh_token_id'], $payload['access_token_id'], $payload['client_id'], $payload['expire_time'])
            || $payload['expire_time'] < time()
            || $this->refreshTokens->isRefreshTokenRevoked($payload['refresh_token_id'])) {
            return null;
        }

        return new PresentedToken(
            type: 'refresh_token',
            accessTokenId: $payload['access_token_id'],
            clientId: $payload['client_id'],
            userId: ($payload['user_id'] ?? null) ?: null,
            scopes: $payload['scopes'] ?? [],
            expiresAt: $payload['expire_time'],
            refreshTokenId: $payload['refresh_token_id'],
        );
    }

    /**
     * The introspection answer for a live token.
     *
     * @return array<string, mixed>
     */
    private function describe(PresentedToken $token): array
    {
        return array_filter([
            'active' => true,
            'scope' => implode(' ', $token->scopes),
            'client_id' => $token->clientId,
            'username' => $this->username($token),
            'token_type' => $token->type === 'access_token' ? 'Bearer' : 'refresh_token',
            'exp' => $token->expiresAt,
            'iat' => $token->issuedAt,
            'sub' => $token->userId,
            'aud' => $token->clientId,
            'iss' => $this->discovery->issuer(),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * The user's email address, when the token's scopes disclose it.
     *
     * Which scope discloses the address is the claims port's decision, so it
     * is asked rather than restated here.
     */
    private function username(PresentedToken $token): ?string
    {
        $user = $token->userId === null ? null : User::query()->find($token->userId);

        return $user === null ? null : ($this->claims->claimsFor($user, $token->scopes, null)['email'] ?? null);
    }
}
