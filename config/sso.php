<?php

declare(strict_types=1);

/*
 * The settings an administrator can change, and the environment variable that
 * pins each one.
 *
 * Declared once, because two facts are read off it and they must agree: what a
 * setting is when nobody has changed it, and whether this deployment fixed it.
 * Spelling the variable names out twice would make a typo read as "not
 * pinned", which fails in the dangerous direction — the value a deployment
 * meant to fix quietly becomes editable.
 *
 * This is also the only place these variables can be read: env() answers null
 * once the configuration is cached, so anywhere else would forget the pin on
 * exactly the installations that cache it.
 */
$pinnable = [
    /* key => [environment variable, default, type] */
    'brand_name' => ['SSO_BRAND_NAME', 'Laravel SSO', 'string'],
    'base_color' => ['SSO_BASE_COLOR', 'neutral', 'string'],
    'accent' => ['SSO_ACCENT', 'default', 'string'],
    'rows_per_page' => ['SSO_ROWS_PER_PAGE', 15, 'int'],
    'sidebar_variant' => ['SSO_SIDEBAR_VARIANT', 'inset', 'string'],
    'auth_layout' => ['SSO_AUTH_LAYOUT', 'split', 'string'],
    'allow_registration' => ['SSO_ALLOW_REGISTRATION', false, 'bool'],
    'require_email_verification' => ['SSO_REQUIRE_EMAIL_VERIFICATION', true, 'bool'],
    'access_token_ttl' => ['SSO_DEFAULT_ACCESS_TOKEN_TTL', 900, 'int'],
    'refresh_token_ttl' => ['SSO_DEFAULT_REFRESH_TOKEN_TTL', 1_209_600, 'int'],
    'id_token_ttl' => ['SSO_DEFAULT_ID_TOKEN_TTL', 900, 'int'],
    'audit_retention_days' => ['SSO_AUDIT_RETENTION_DAYS', 365, 'int'],
];

/*
 * Read here rather than through config('sso.demo.enabled'), because this file
 * is what defines that value and is not yet loaded while it runs.
 */
$demo = (bool) env('SSO_DEMO_MODE', false);

/*
 * What a public demonstration fixes, whatever the environment says.
 *
 * Declared once for the same reason $pinnable is: the same two facts are read
 * off it below, and a key spelled out twice would eventually be forced without
 * being pinned, which fails in the dangerous direction. A demo is signed into
 * by strangers, so it must never become a way to send mail to an address one
 * of them chose, and leaving the switch editable would let the first visitor
 * turn it back on.
 */
$forced = $demo ? ['require_email_verification' => false] : [];

$configured = fn (array $setting): mixed => match ($setting[2]) {
    'int' => (int) env($setting[0], $setting[1]),
    'bool' => (bool) env($setting[0], $setting[1]),
    default => (string) env($setting[0], $setting[1]),
};

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
    | Discovery Document
    |--------------------------------------------------------------------------
    |
    | Where relying parties fetch this server's configuration. It is derived
    | from the issuer rather than from route(), because the issuer is the
    | canonical public URL and may differ from APP_URL behind a proxy.
    |
    */

    'discovery_url' => rtrim((string) env('SSO_ISSUER', env('APP_URL')), '/').'/.well-known/openid-configuration',

    /*
    |--------------------------------------------------------------------------
    | OAuth2 Defaults
    |--------------------------------------------------------------------------
    |
    | "require_pkce" requires Proof Key for Code Exchange on every authorization
    | code request. The underlying OAuth2 server already enforces it for public
    | clients unconditionally, so turning this off only relaxes the requirement
    | for confidential clients; it can never weaken a public one.
    |
    | Token lifetimes are not here: they are operator settings, and the value
    | actually in force lives in "oidc-server.tokens", which is what the issuer
    | reads. A second copy here would be one nobody consumes.
    |
    */

    'oauth' => [
        'require_pkce' => env('SSO_PKCE_REQUIRED', true),
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
    | Demonstration Mode
    |--------------------------------------------------------------------------
    |
    | A public demo is signed into by strangers, and everything they can reach
    | they can also change. "sso:demo-reset" drops the database and rebuilds
    | the sample data, and the scheduler runs it every "reset_hours" while
    | this is on.
    |
    | Off by default, and checked before anything is dropped: turning it on is
    | the deliberate act of saying this installation holds nothing worth
    | keeping.
    |
    | Turning it on also closes the two things a stranger could otherwise
    | abuse. No mail leaves the server, and email verification is forced off
    | and pinned, so nobody is stranded behind a message that will never
    | arrive. Each reset gives the administrator a new password, published on
    | the sign-in page by App\Services\DemoMode.
    |
    */

    'demo' => [
        'enabled' => $demo,
        'reset_hours' => (int) env('SSO_DEMO_RESET_HOURS', 6),
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

    /*
    |--------------------------------------------------------------------------
    | Operator Settings
    |--------------------------------------------------------------------------
    |
    | What every setting under Settings → App settings is when nobody has
    | changed it. App\Services\Settings merges the stored rows over these and
    | deletes a row that matches one, so the table records decisions rather
    | than a copy of this file — raising a default here still reaches an
    | installation that left that setting alone.
    |
    | Nothing writes back to this block. The values an administrator chose are
    | copied into the configuration their consumers read — "app.name",
    | "session.lifetime", "fortify.features", "oidc-server.tokens" — so that
    | "what shipped" stays a fixed reference to compare against.
    |
    */

    'defaults' => [
        ...array_map($configured, $pinnable),
        ...$forced,

        /* Uploaded imagery, which has no sensible default. */
        'brand_logo' => null,
        'auth_background' => null,

        /*
         * The links a fresh install starts with. They are defaults rather than
         * fixtures: an operator can reword one, move their own runbook above
         * them, or remove them entirely.
         *
         * This server's own documentation is served from it, so the URL is
         * built from APP_URL rather than written down — an installation on
         * another domain still points at its own copy.
         */
        'documentation_links' => [
            [
                'label' => 'Documentation',
                'url' => rtrim((string) config('app.url'), '/').'/docs',
            ],
            [
                'label' => 'GitHub',
                'url' => 'https://github.com/lauroguedes/laravel-sso',
            ],
            [
                'label' => 'OpenID Connect',
                'url' => 'https://openid.net/developers/how-connect-works/',
            ],
        ],

        /*
         * Follows SESSION_LIFETIME, the same variable "config/session.php"
         * reads, so a deployment that sets it moves the default. It is not
         * pinnable: every Laravel skeleton ships that line uncommented, and
         * pinning it would lock the field on every installation.
         */
        'session_lifetime' => (int) env('SESSION_LIFETIME', 120),

        'logout_other_sessions_on_password_change' => true,

        /*
         * The consent screen. Not pinnable: the wording is the operator's to
         * change, and nothing about a deployment decides it.
         *
         * "{application}" is replaced with the name of the application asking.
         * A message left empty falls back to the application's description,
         * and a scope with no wording of its own uses its description in
         * "config/oidc-server.php".
         */
        'consent_heading' => 'Continue to {application}',
        'consent_message' => null,
        'consent_scope_descriptions' => [],
        'consent_show_account' => true,
        'consent_remember_approvals' => true,
        'consent_privacy_url' => null,
        'consent_terms_url' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pinned Settings
    |--------------------------------------------------------------------------
    |
    | The settings this deployment fixed through the environment. The interface
    | shows each as fixed rather than offering to change it, and refuses one
    | submitted anyway — otherwise an administrator would save an edit that the
    | next deploy silently reverts.
    |
    */

    'pinned' => array_keys([
        ...array_filter(
            $pinnable,
            fn (array $setting): bool => env($setting[0]) !== null,
        ),
        ...$forced,
    ]),

];
