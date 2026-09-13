<?php

declare(strict_types=1);

namespace App\Oidc\Adapters\Native;

use App\Oidc\Contracts\DiscoversProvider;
use App\Services\ScopeRegistry;
use Illuminate\Contracts\Config\Repository;

/**
 * The discovery document, built from the protocol configuration.
 *
 * Every value comes from "config/oidc-server.php", so what this server
 * advertises and what it enforces are read from the same place. The one
 * exception is introspection, which never offers "none" because
 * IntrospectionController refuses public clients.
 */
class Discovery implements DiscoversProvider
{
    /**
     * The claims every ID Token may carry, whatever the scopes.
     */
    private const PROTOCOL_CLAIMS = ['sub', 'iss', 'aud', 'exp', 'iat', 'auth_time'];

    public function __construct(
        private readonly Repository $config,
        private readonly ScopeRegistry $scopes,
    ) {}

    public function metadata(): array
    {
        $protocol = (array) $this->config->get('oidc-server');
        $issuer = rtrim((string) $protocol['issuer'], '/');

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
            'claims_supported' => array_values(array_unique([
                ...self::PROTOCOL_CLAIMS,
                ...array_merge(...array_column((array) $protocol['scopes'], 'claims')),
            ])),
            'code_challenge_methods_supported' => $protocol['code_challenge_methods_supported'],
            'grant_types_supported' => $protocol['grant_types_supported'],
            'introspection_endpoint_auth_methods_supported' => array_values(array_diff($protocol['token_endpoint_auth_methods_supported'], ['none'])),
            'revocation_endpoint_auth_methods_supported' => $protocol['token_endpoint_auth_methods_supported'],
        ];
    }
}
