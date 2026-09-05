# Configuration

Almost everything is an environment variable. The two configuration files that
matter are `config/sso.php`, which holds the settings an operator changes, and
`config/oidc-server.php`, which describes the protocol surface.

Nothing here needs editing to run the server. Set the issuer, and the defaults
are the ones you want.

## Identity

### `SSO_ISSUER`

The canonical, publicly reachable URL of this server — the value that appears
as the `iss` claim in every ID Token, and that every client validates. Defaults
to `APP_URL`.

Set this explicitly whenever `APP_URL` is not what the outside world sees, such
as behind a terminating proxy or a load balancer.

Changing it after applications are integrated invalidates their expectations:
clients that cached the discovery document will reject tokens whose `iss` no
longer matches. Treat it as fixed once you are live.

## Accounts

### `SSO_ALLOW_REGISTRATION` (default `false`)

Whether anyone can create their own account at `/register`. An identity
provider is rarely open to the public, so this is off, and administrators
create users instead. Turning it on enables Fortify's registration feature and
the route with it.

### `SSO_REQUIRE_EMAIL_VERIFICATION` (default `true`)

Whether users must confirm their address before they can use the server.

This is read by `config/fortify.php`, which decides whether the feature exists
at all. Ask Fortify whether verification is enabled rather than reading the
environment variable yourself, or the two answers drift apart. Turn it off if
you have no mail transport, and be aware that you are then accepting
unverified addresses as identity.

## OAuth 2.0

### `SSO_PKCE_REQUIRED` (default `true`)

Requires Proof Key for Code Exchange on every authorization code request.

The underlying OAuth 2.0 server already enforces PKCE for public clients
unconditionally, so turning this off only relaxes the requirement for
confidential ones. It can never weaken a public client. Leave it on unless you
have a legacy confidential client that cannot be changed.

### Token lifetimes

| Variable                        | Default             |                                                                       |
| ------------------------------- | ------------------- | --------------------------------------------------------------------- |
| `SSO_DEFAULT_ACCESS_TOKEN_TTL`  | `900` (15 minutes)  | How long an access token is accepted                                  |
| `SSO_DEFAULT_REFRESH_TOKEN_TTL` | `1209600` (14 days) | How long a client may exchange a refresh token for a new access token |
| `SSO_DEFAULT_ID_TOKEN_TTL`      | `900` (15 minutes)  | How long an ID Token is valid                                         |

All in seconds. Short access tokens with longer refresh tokens is the right
shape: a leaked access token expires quickly, and revoking the session
invalidates the refresh token that would have renewed it.

## Redirect URIs

Redirect URIs are **always matched exactly**. Wildcards are not supported at
any setting, deliberately: a wildcard redirect is how authorization codes end
up delivered to somebody else's host.

| Variable                      | Default |                                                                                            |
| ----------------------------- | ------- | ------------------------------------------------------------------------------------------ |
| `SSO_REQUIRE_HTTPS_REDIRECTS` | `true`  | Reject `http://` redirect URIs                                                             |
| `SSO_ALLOW_INSECURE_LOOPBACK` | `true`  | Except on `localhost`, `127.0.0.1` and `[::1]`, so native and CLI clients can be developed |

An application may register at most 20 redirect URIs.

## Rate limits

Requests per minute for the protocol endpoints, keyed by client IP.

| Variable                   | Default | Guards                                                      |
| -------------------------- | ------- | ----------------------------------------------------------- |
| `SSO_RATE_LIMIT_DISCOVERY` | `120`   | The discovery document, the key set, and `/oauth/authorize` |
| `SSO_RATE_LIMIT_TOKEN`     | `30`    | `/oauth/token`                                              |
| `SSO_RATE_LIMIT_USERINFO`  | `60`    | `/oauth/userinfo`                                           |

The discovery limit is the most generous because real users reach
`/oauth/authorize` from browsers, often behind a shared address. The token
endpoint is the sensitive one: it is where a stolen authorization code or
client secret would be redeemed.

Sign-in attempts are throttled separately by Fortify, keyed by email address
and IP together.

## Audit trail

| Variable                   | Default |                                                                              |
| -------------------------- | ------- | ---------------------------------------------------------------------------- |
| `SSO_AUDIT_RETENTION_DAYS` | `365`   | Entries older than this are removed by the daily `activitylog:clean` command |
| `ACTIVITYLOG_ENABLED`      | `true`  | Set to `false` to stop recording entirely                                    |

Retention only happens if the scheduler is running. See
[Deployment](deployment.md).

`ACTIVITYLOG_BUFFER_ENABLED` is a third, deliberately absent from
`.env.example`. It holds entries in memory and writes them in one bulk insert
at the end of the request, which trades durability for throughput: a fatal
error loses whatever had not been flushed, and on this server that could be the
record of the sign-in that preceded it. The columns this project adds — the
application, the address and the user agent — do survive the buffer, so
enabling it is safe in that respect if you decide the trade is worth making.

## Scopes and claims

Defined in `config/oidc-server.php`, not by an environment variable, because
adding one is a decision about what this server discloses rather than a
deployment setting.

```php
'scopes' => [
    'openid'  => ['claims' => ['sub']],
    'profile' => ['claims' => ['name', 'updated_at']],
    'email'   => ['claims' => ['email', 'email_verified']],
    'roles'   => ['claims' => ['roles', 'permissions']],
],
```

The list is authoritative in both directions: it is what the discovery document
advertises, what an application may be granted, and what a token may carry. To
publish a new claim, add it to a scope here and teach `User::resolveOidcClaim()`
how to produce it.

Claim resolution is a method rather than a closure in configuration, because
closures cannot survive `config:cache` — and a production server should always
have its configuration cached.

## Protocol surface

Also in `config/oidc-server.php`, and hardened relative to the package's
defaults:

- `response_types_supported` is `['code']` only.
- The implicit grant and the resource owner password credentials grant are not
  present and must not be added. Both hand credentials or tokens to places they
  should never reach.
- `code_challenge_methods_supported` is `['S256']` — `plain` is not offered.
- `token_endpoint_auth_methods_supported` includes `none`, which is how a
  public client, holding no secret, authenticates.

## Caching configuration

In production:

```bash
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

Run these again after every deployment and every change to `.env`. A cached
configuration ignores the environment file entirely, which is the usual
explanation for a setting that appears not to take effect.
