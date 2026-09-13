<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| OpenID Connect
|--------------------------------------------------------------------------
|
| The protocol surface of this server: the scopes and the claims they
| disclose, token lifetimes, and what discovery advertises. The OpenID
| Connect layer in app/Oidc reads it, and App\Providers\OidcServiceProvider
| configures Passport from it.
|
| The issuer and application level settings live in "config/sso.php". Where
| both files need the same value they read the same SSO_* environment
| variable, so operators only ever set it in one place.
|
| Every value here must remain serializable: closures would break
| "php artisan config:cache". Claim values are resolved in
| App\Models\User::resolveOidcClaim() instead.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Scopes
    |--------------------------------------------------------------------------
    |
    | The built-in OpenID Connect scopes. Custom application scopes are added
    | on top of these; scopes stay conceptually separate from roles.
    |
    */

    'scopes' => [
        'openid' => [
            'description' => 'Verify your identity',
            'claims' => ['sub'],
        ],
        'profile' => [
            'description' => 'Access your name and profile information',
            'claims' => ['name', 'updated_at'],
        ],
        'email' => [
            'description' => 'Access your email address',
            'claims' => ['email', 'email_verified'],
        ],
        /*
         * Authorization claims are opt in. An application only learns the
         * role and permissions a user holds *in that application* when it has
         * been granted this scope, and never learns anything about any other
         * application. See App\Oidc\AuthorizationClaims.
         */
        'roles' => [
            'description' => 'Access your role and permissions in this application',
            'claims' => ['roles', 'permissions'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Scopes
    |--------------------------------------------------------------------------
    */

    'default_scopes' => ['openid'],

    /*
    |--------------------------------------------------------------------------
    | Token Configuration
    |--------------------------------------------------------------------------
    */

    'tokens' => [
        'access_token_ttl' => (int) env('SSO_DEFAULT_ACCESS_TOKEN_TTL', 900),
        'refresh_token_ttl' => (int) env('SSO_DEFAULT_REFRESH_TOKEN_TTL', 1_209_600),
        'id_token_ttl' => (int) env('SSO_DEFAULT_ID_TOKEN_TTL', 900),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Response Types
    |--------------------------------------------------------------------------
    |
    | Authorization Code only. The Implicit Grant ("token") is deliberately not
    | advertised and not enabled: it returns tokens through the front channel
    | and is discouraged by current OAuth2 security guidance.
    |
    */

    'response_types_supported' => [
        'code',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Grant Types
    |--------------------------------------------------------------------------
    |
    | Interactive clients use authorization_code (with PKCE) and refresh_token.
    | Machine-to-machine clients use client_credentials. The Resource Owner
    | Password Credentials grant is never enabled.
    |
    */

    'grant_types_supported' => [
        'authorization_code',
        'refresh_token',
        'client_credentials',
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Endpoint Auth Methods
    |--------------------------------------------------------------------------
    |
    | "none" advertises support for public clients, which authenticate with
    | PKCE instead of a client secret.
    |
    */

    'token_endpoint_auth_methods_supported' => [
        'client_secret_basic',
        'client_secret_post',
        'none',
    ],

    /*
    |--------------------------------------------------------------------------
    | ID Token Signing Algorithms
    |--------------------------------------------------------------------------
    */

    'id_token_signing_alg_values_supported' => [
        'RS256',
    ],

    /*
    |--------------------------------------------------------------------------
    | Subject Types
    |--------------------------------------------------------------------------
    */

    'subject_types_supported' => [
        'public',
    ],

    /*
    |--------------------------------------------------------------------------
    | PKCE Code Challenge Methods
    |--------------------------------------------------------------------------
    |
    | S256 only. The "plain" method offers no protection against an attacker
    | who can already observe the authorization request.
    |
    */

    'code_challenge_methods_supported' => [
        'S256',
    ],

    /*
    |--------------------------------------------------------------------------
    | Post Logout Redirect URIs
    |--------------------------------------------------------------------------
    */

    'post_logout_redirect_uris_supported' => [],

];
