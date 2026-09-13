<?php

declare(strict_types=1);

namespace App\Oidc\Adapters\Native;

use App\Oidc\Contracts\DiscoversProvider;
use App\Oidc\Contracts\ResolvesClaims;
use App\Services\ScopeRegistry;
use Illuminate\Contracts\Config\Repository;

/**
 * The discovery document, built from the protocol configuration.
 *
 * Every value comes from "config/oidc-server.php", or from the code that acts
 * on it, so what this server advertises and what it does cannot drift.
 * Introspection never offers "none", because IntrospectionController refuses
 * public clients.
 */
class Discovery implements DiscoversProvider
{
    public function __construct(
        private readonly Repository $config,
        private readonly ScopeRegistry $scopes,
        private readonly ResolvesClaims $claims,
    ) {}

    public function issuer(): string
    {
        return rtrim((string) $this->config->get('oidc-server.issuer'), '/');
    }

    public function metadata(): array
    {
        $protocol = (array) $this->config->get('oidc-server');
        $issuer = $this->issuer();

        return [
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer.'/oauth/authorize',
            'token_endpoint' => $issuer.'/oauth/token',
            'userinfo_endpoint' => $issuer.'/oauth/userinfo',
            'jwks_uri' => $issuer.'/.well-known/jwks.json',
            'end_session_endpoint' => $issuer.'/oauth/logout',
            'introspection_endpoint' => $issuer.'/oauth/introspect',
            'revocation_endpoint' => $issuer.'/oauth/revoke',
            'post_logout_redirect_uris_supported' => $protocol['post_logout_redirect_uris_supported'],
            'response_types_supported' => $protocol['response_types_supported'],
            'subject_types_supported' => $protocol['subject_types_supported'],
            'id_token_signing_alg_values_supported' => $protocol['id_token_signing_alg_values_supported'],
            'scopes_supported' => $this->scopes->ids(),
            'token_endpoint_auth_methods_supported' => $protocol['token_endpoint_auth_methods_supported'],
            'claims_supported' => $this->claims->supportedClaims(),
            'code_challenge_methods_supported' => $protocol['code_challenge_methods_supported'],
            'grant_types_supported' => $protocol['grant_types_supported'],
            'introspection_endpoint_auth_methods_supported' => array_values(array_diff($protocol['token_endpoint_auth_methods_supported'], ['none'])),
            'revocation_endpoint_auth_methods_supported' => $protocol['token_endpoint_auth_methods_supported'],
        ];
    }
}
