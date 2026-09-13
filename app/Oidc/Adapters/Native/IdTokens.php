<?php

declare(strict_types=1);

namespace App\Oidc\Adapters\Native;

use App\Oidc\Contracts\DiscoversProvider;
use App\Oidc\Contracts\IdTokenRequest;
use App\Oidc\Contracts\IssuesIdTokens;
use App\Oidc\Contracts\ProvidesSigningKeys;
use App\Oidc\Contracts\ResolvesClaims;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;

/**
 * ID Tokens signed with the key Passport and the key set share.
 *
 * Each token names its key in a "kid" header, states the issuer exactly as the
 * discovery document does, expires after the ID Token lifetime from App
 * settings rather than with the access token, and carries the claims the
 * granted scopes disclose to that client.
 */
class IdTokens implements IssuesIdTokens
{
    public function __construct(
        private readonly ProvidesSigningKeys $keys,
        private readonly ResolvesClaims $claims,
        private readonly DiscoversProvider $discovery,
        private readonly Repository $config,
    ) {}

    public function issue(IdTokenRequest $request): string
    {
        $issuer = $this->discovery->issuer();
        $subject = $request->user->getOidcSubject();
        $clientId = $request->clientId;

        if ($issuer === '' || $subject === '' || $clientId === '') {
            throw new InvalidArgumentException('An ID Token needs an issuer, a subject and a client.');
        }

        $now = now()->toImmutable();

        $builder = Builder::new(new JoseEncoder, ChainedFormatter::default())
            ->withHeader('kid', $this->keys->keyId())
            ->issuedBy($issuer)
            ->permittedFor($clientId)
            ->issuedAt($now)
            ->expiresAt($now->addSeconds((int) $this->config->get('oidc-server.tokens.id_token_ttl')))
            ->relatedTo($subject);

        if ($request->authTime !== null) {
            $builder = $builder->withClaim('auth_time', $request->authTime);
        }

        if ($request->nonce !== null) {
            $builder = $builder->withClaim('nonce', $request->nonce);
        }

        foreach ($this->claims->claimsFor($request->user, $request->scopes, $clientId) as $claim => $value) {
            if ($claim !== '' && $claim !== 'sub') {
                $builder = $builder->withClaim($claim, $value);
            }
        }

        return $builder->getToken(new Sha256, InMemory::plainText($this->keys->privateKey()))->toString();
    }
}
