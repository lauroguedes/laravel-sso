<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Issuer
    |--------------------------------------------------------------------------
    |
    | The canonical, publicly reachable URL of this Identity Provider. Every
    | OpenID Connect client validates the "iss" claim of an ID Token against
    | this exact value, so it must match the URL clients are redirected to.
    |
    | This same variable drives "oidc-server.issuer". Keep them in sync by
    | configuring SSO_ISSUER rather than editing either file.
    |
    */

    'issuer' => env('SSO_ISSUER', env('APP_URL')),

    /*
    |--------------------------------------------------------------------------
    | Registration & Verification
    |--------------------------------------------------------------------------
    |
    | An Identity Provider is rarely open to the public, so self-registration
    | is disabled by default and administrators create users instead. These
    | values toggle the matching Fortify features in "config/fortify.php".
    |
    */

    'registration' => [
        'enabled' => env('SSO_ALLOW_REGISTRATION', false),
        'require_email_verification' => env('SSO_REQUIRE_EMAIL_VERIFICATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth2 Defaults
    |--------------------------------------------------------------------------
    |
    | Token lifetimes are expressed in seconds and are handed to Passport by
    | the OIDC server package, which reads the same environment variables.
    |
    | "require_pkce" is the intended policy for every authorization code
    | request. The underlying OAuth2 server already enforces PKCE for public
    | clients unconditionally; extending that to confidential clients needs a
    | custom grant and is not wired up yet, so today this flag documents intent
    | for confidential clients rather than enforcing it.
    |
    */

    'oauth' => [
        'require_pkce' => env('SSO_PKCE_REQUIRED', true),
        'access_token_ttl' => (int) env('SSO_DEFAULT_ACCESS_TOKEN_TTL', 900),
        'refresh_token_ttl' => (int) env('SSO_DEFAULT_REFRESH_TOKEN_TTL', 1_209_600),
        'id_token_ttl' => (int) env('SSO_DEFAULT_ID_TOKEN_TTL', 900),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirect URI Policy
    |--------------------------------------------------------------------------
    |
    | Redirect URIs are always matched exactly; wildcards are never supported.
    | Outside of local development we additionally require HTTPS, with an
    | explicit carve-out for loopback addresses so native and CLI clients
    | can still complete the authorization code flow during development.
    |
    */

    'redirect_uris' => [
        'require_https' => env('SSO_REQUIRE_HTTPS_REDIRECTS', true),
        'allow_insecure_loopback' => env('SSO_ALLOW_INSECURE_LOOPBACK', true),
        'loopback_hosts' => ['localhost', '127.0.0.1', '[::1]'],
        'max_per_application' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | Requests per minute for the OAuth2 / OpenID Connect protocol endpoints,
    | keyed by client IP. The "discovery" limit also guards /oauth/authorize,
    | which real users hit from browsers behind shared addresses, so it is the
    | most generous. The token endpoint is the sensitive one.
    |
    */

    'rate_limits' => [
        'discovery' => (int) env('SSO_RATE_LIMIT_DISCOVERY', 120),
        'token' => (int) env('SSO_RATE_LIMIT_TOKEN', 30),
        'userinfo' => (int) env('SSO_RATE_LIMIT_USERINFO', 60),
    ],

];
