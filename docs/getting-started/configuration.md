---
title: 'Configuration'
description: 'Every environment variable, and which settings an administrator owns instead.'
order: 2
---

Almost everything is an environment variable. The two configuration files that
matter are `config/sso.php`, which holds the defaults an operator changes, and
`config/oidc-server.php`, which describes the protocol surface.

Nothing here needs editing to run the server. Set the issuer, and the defaults
are the ones you want.

## Settings, and pinning them

Many of the variables below are only the **default** for a setting an
administrator can change from **Settings → App settings**. Only what they
actually change is stored, so raising a default here still reaches an
installation that left that setting alone.

Setting the variable **pins** it: the interface shows the value as fixed and
refuses one submitted anyway, so a deployment that manages its own
configuration cannot have it edited away. Every pinnable variable appears in
`.env.example`, commented out, beside the settings it belongs to; the
authoritative list is `pinned` in `config/sso.php`.

`SSO_ISSUER`, the redirect URI policy and the rate limits are not settings.
They are protocol and deployment decisions, and stay in the environment.

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

Both are settings, on the **Access** tab.

### `SSO_ALLOW_REGISTRATION` (default `false`)

Whether anyone can create their own account at `/register`. An identity
provider is rarely open to the public, so this is off, and administrators
create users instead.

Turning it on adds Fortify's registration feature and the route with it — with
it off the page does not exist rather than being hidden. That is decided before
Fortify registers its routes, so the switch takes effect on the next request
rather than needing a redeploy.

### `SSO_REQUIRE_EMAIL_VERIFICATION` (default `true`)

Whether users must confirm their address before they can use the server.

Like registration, this decides whether the feature exists at all. Ask Fortify
whether verification is enabled rather than reading the setting yourself, or
the two answers drift apart. Turn it off if you have no mail transport, and be
aware that you are then accepting unverified addresses as identity.

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

All in seconds, and all three are settings on the **Access** tab, where the
field shows what the number comes to — `1209600` reads as `14 days`.

Short access tokens with longer refresh tokens is the right shape: a leaked
access token expires quickly, and revoking the session invalidates the refresh
token that would have renewed it.

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

Retention is a setting on the **Access** tab. Whether recording happens at all
is not: switching off the audit trail from inside the interface would be the
first thing worth doing to it.

Retention only happens if the scheduler is running. See
[Deployment](/docs/getting-started/deployment).

`ACTIVITYLOG_BUFFER_ENABLED` is a third, deliberately absent from
`.env.example`. It holds entries in memory and writes them in one bulk insert
at the end of the request, which trades durability for throughput: a fatal
error loses whatever had not been flushed, and on this server that could be the
record of the sign-in that preceded it. The columns this project adds — the
application, the address and the user agent — do survive the buffer, so
enabling it is safe in that respect if you decide the trade is worth making.

## Appearance and layout

Settings, on the **Brand**, **Appearance** and **Layout** tabs. The six below
are pinnable; the uploaded logo, the sign-in background and the reference links
on the **Links** tab are not, having no sensible value to express as a
variable.

| Variable              |                                                               |
| --------------------- | ------------------------------------------------------------- |
| `SSO_BRAND_NAME`      | What this server calls itself, including in the mail it sends |
| `SSO_BASE_COLOR`      | `neutral`, `zinc`, `slate`, `stone` or `gray`                 |
| `SSO_ACCENT`          | The one colour the interface draws attention with             |
| `SSO_ROWS_PER_PAGE`   | Default page size for listings                                |
| `SSO_SIDEBAR_VARIANT` | `inset`, `sidebar` or `floating`                              |
| `SSO_AUTH_LAYOUT`     | `simple`, `card` or `split`                                   |

The palette is a set of CSS custom properties written into the page, which is
all a shadcn theme is. Nothing is fetched from anywhere, and there is no build
step: an administrator picks a colour and the next page is that colour.

The brand name becomes `APP_NAME` at runtime, which is what puts it in the
greeting and signature of every message. Uploaded imagery lives on the public
disk, so `php artisan storage:link` must have been run — `sso:install` does not
do it for you.

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
