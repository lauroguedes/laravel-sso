<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| OpenID Connect Protocol Configuration
|--------------------------------------------------------------------------
|
| This file configures admin9/laravel-oidc-server, which layers Discovery,
| JWKS, UserInfo, Introspection, Revocation and RP-Initiated Logout on top
| of Laravel Passport. It describes the *protocol* surface of this server.
|
| Application level settings live in "config/sso.php". Where both files need
| the same value they read the same SSO_* environment variable, so operators
| only ever set it in one place.
|
| Every value here must remain serializable: closures would break
| "php artisan config:cache". Computed claims are resolved in
| App\Models\User::resolveOidcClaim() instead.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | OIDC Issuer
    |--------------------------------------------------------------------------
    */

    'issuer' => env('SSO_ISSUER', env('APP_URL')),

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | This must name a concrete class. The package reads it with a config()
    | default, which only applies when the key is absent, so leaving it null
    | fails when an id_token is generated rather than falling back to the
    | auth provider's model.
    |
    */

    'user_model' => User::class,

    /*
    |--------------------------------------------------------------------------
    | Passport Auto-Configuration
    |--------------------------------------------------------------------------
    |
    | The package configures Passport's scopes, token lifetimes, client model,
    | authorization view and the token response type that injects id_token.
    | Leaving this enabled keeps a single owner for that wiring; anything we
    | need to add on top is applied in App\Providers\SsoServiceProvider.
    |
    */

    'configure_passport' => true,

    /*
    |--------------------------------------------------------------------------
    | Ignore Passport Routes
    |--------------------------------------------------------------------------
    |
    | The package registers the full endpoint set itself (including Passport's
    | own authorize and token controllers), so Passport must not also register
    | them. Turning this off would produce duplicate route names.
    |
    */

    'ignore_passport_routes' => true,

    /*
    |--------------------------------------------------------------------------
    | Authorization View
    |--------------------------------------------------------------------------
    |
    | The package's Blade consent screen. This is replaced by an Inertia page
    | when the authorization flow is built out.
    |
    */

    'authorization_view' => 'oidc-server::authorize',

    /*
    |--------------------------------------------------------------------------
    | Client Model
    |--------------------------------------------------------------------------
    |
    | App\Models\Application extends the package's OidcClient, which in turn
    | extends Passport's Client. Administrator-managed applications ARE these
    | records; there is no second notion of an OAuth client.
    |
    */

    'client_model' => Application::class,

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
         * application. See App\Services\ApplicationClaimsService.
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
    | Claims Resolver
    |--------------------------------------------------------------------------
    |
    | Maps a claim name to a model attribute. Anything that needs computing is
    | handled by App\Models\User::resolveOidcClaim() so this file stays
    | cacheable.
    |
    */

    'claims_resolver' => [],

    /*
    |--------------------------------------------------------------------------
    | Default Claims Map
    |--------------------------------------------------------------------------
    |
    | Plain attribute mappings only. "email_verified" and "updated_at" are
    | derived values and are resolved on the User model.
    |
    */

    'default_claims_map' => [
        'name' => 'name',
        'email' => 'email',
    ],

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

    /*
    |--------------------------------------------------------------------------
    | Routes Configuration
    |--------------------------------------------------------------------------
    |
    | Rate limiters are defined in App\Providers\SsoServiceProvider and are
    | tuned through the "sso.rate_limits" configuration.
    |
    | Note: the package applies "discovery_middleware" to the /oauth/authorize
    | routes as well as to the /.well-known endpoints, so that limiter is sized
    | for interactive browser traffic behind shared IP addresses.
    |
    */

    'routes' => [
        'enabled' => true,
        'discovery_middleware' => ['throttle:sso-discovery'],
        'token_middleware' => ['throttle:sso-token'],
        'userinfo_middleware' => ['auth:api', 'throttle:sso-userinfo'],
    ],

];
