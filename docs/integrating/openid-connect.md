---
title: 'OpenID Connect'
description: 'The protocol surface in detail: endpoints, scopes, claims and flows.'
order: 2
---

This server implements OpenID Connect Core on top of OAuth 2.0. Any conforming
client library will work against it; nothing here is Laravel-specific.

## Discovery

```
GET https://auth.example.com/.well-known/openid-configuration
```

Point your client library at the issuer and let it read this. Everything below
is described there, so a change to the server's configuration reaches clients
without anyone editing them.

## Endpoints

|               |                            |
| ------------- | -------------------------- |
| Issuer        | `https://auth.example.com` |
| Authorization | `/oauth/authorize`         |
| Token         | `/oauth/token`             |
| UserInfo      | `/oauth/userinfo`          |
| Key set       | `/.well-known/jwks.json`   |
| Introspection | `/oauth/introspect`        |
| Revocation    | `/oauth/revoke`            |
| End session   | `/oauth/logout`            |

## What is supported

|                       |                                                             |
| --------------------- | ----------------------------------------------------------- |
| Response types        | `code`                                                      |
| Grant types           | `authorization_code`, `refresh_token`, `client_credentials` |
| PKCE                  | `S256` (`plain` is not offered)                             |
| ID Token signing      | `RS256`                                                     |
| Subject type          | `public`                                                    |
| Client authentication | `client_secret_basic`, `client_secret_post`, `none`         |

The implicit grant and the resource owner password credentials grant are **not
supported and will not be added**. The first returns tokens in a URL fragment,
where they end up in browser history and referrer headers; the second requires
the client to handle the user's password, which is the thing this server exists
to avoid.

## Scopes and claims

| Scope     | Claims                    |                                                                           |
| --------- | ------------------------- | ------------------------------------------------------------------------- |
| `openid`  | `sub`                     | Required. Its presence is what makes a request an OpenID Connect request. |
| `profile` | `name`, `updated_at`      |                                                                           |
| `email`   | `email`, `email_verified` |                                                                           |
| `roles`   | `roles`, `permissions`    | What the user holds **in the requesting application**                     |

An application only receives the scopes it was granted at registration, and
only those the user consented to.

`roles` and `permissions` are scoped to the client asking. Two applications
sending the same user through the same flow get different values, and neither
learns anything about the other. See [Authorization](/docs/administration/authorization).

## Authorization code with PKCE

The flow every interactive application should use, whether or not it holds a
secret.

**1. Send the user to the authorization endpoint.**

```
GET /oauth/authorize
  ?client_id=9f3c2b...
  &redirect_uri=https://app.example.com/auth/callback
  &response_type=code
  &scope=openid%20profile%20email
  &state=<random, checked on return>
  &code_challenge=<BASE64URL(SHA256(verifier))>
  &code_challenge_method=S256
```

The user signs in if they have not already, and approves the request unless the
application is configured to skip consent. The browser comes back to your
redirect URI with `?code=...&state=...`.

Check that `state` matches what you sent. That is what makes the response
yours rather than an attacker's.

**2. Exchange the code for tokens.**

```bash
curl -X POST https://auth.example.com/oauth/token \
  -d grant_type=authorization_code \
  -d client_id=9f3c2b... \
  -d client_secret=<omit for a public client> \
  -d redirect_uri=https://app.example.com/auth/callback \
  -d code=<the code> \
  -d code_verifier=<the original verifier>
```

```json
{
    "token_type": "Bearer",
    "expires_in": 900,
    "access_token": "eyJ...",
    "refresh_token": "def...",
    "id_token": "eyJ..."
}
```

**3. Validate the ID Token.** Your library does this; if you are doing it by
hand, all of it is mandatory:

- The signature, against the key from `/.well-known/jwks.json` whose `kid`
  matches the token header.
- `iss` equals the issuer, exactly.
- `aud` contains your client ID.
- `exp` is in the future, `iat` is not implausibly old.
- `nonce` matches the one you sent, if you sent one.

An ID Token says who signed in. It is not an API credential — do not send it to
your own backend as a bearer token, and do not accept one there.

## Refreshing

```bash
curl -X POST https://auth.example.com/oauth/token \
  -d grant_type=refresh_token \
  -d refresh_token=<the refresh token> \
  -d client_id=9f3c2b... \
  -d client_secret=<omit for a public client>
```

Access tokens last 15 minutes by default and refresh tokens 14 days. A refresh
token stops working the moment an administrator revokes it — from the
**Sessions** page, with **Sign out everywhere** on the user, with **Revoke
issued tokens** on the application, or by disabling the application. Revoking
an access token revokes the refresh token issued with it.

Disabling a user revokes their tokens too, in the same action. See
[Authorization](/docs/administration/authorization#disabled-users).

## Machine to machine

No user, no browser, no refresh token:

```bash
curl -X POST https://auth.example.com/oauth/token \
  -d grant_type=client_credentials \
  -d client_id=... \
  -d client_secret=... \
  -d scope=...
```

The response carries no `id_token`, because there is nobody to describe.

## UserInfo

```bash
curl https://auth.example.com/oauth/userinfo \
  -H "Authorization: Bearer <access token>"
```

Returns the claims the token's scopes allow, for the user the token belongs to.
Use it when you want fresh values; the ID Token is a snapshot from sign-in
time.

## Introspection and revocation

```bash
curl -X POST https://auth.example.com/oauth/introspect \
  -u client_id:client_secret -d token=<token>

curl -X POST https://auth.example.com/oauth/revoke \
  -u client_id:client_secret -d token=<token>
```

Introspection answers whether a token is still live. A resource server that
cannot afford to honour a revoked token for the rest of its 15-minute lifetime
should introspect rather than trust the expiry.

## Signing keys

The key set is published at `/.well-known/jwks.json`, and every ID Token
carries a `kid` header naming the key that signed it, so clients can cache the
set and still follow a rotation.

The private key lives at `storage/oauth-private.key`. It is not in version
control, and `sso:install` never replaces an existing one. Rotating it
invalidates every ID Token in flight and every cached key set — see
[Deployment](/docs/getting-started/deployment#signing-keys).

## Logout

```
GET /oauth/logout
  ?id_token_hint=<the ID Token>
  &post_logout_redirect_uri=https://app.example.com/signed-out
  &state=<echoed back to the landing page>
```

This ends the session on **this server**, always — that part does not depend on
getting anything else right.

`post_logout_redirect_uri` must be **registered on the application, in its
post-logout list, and is matched exactly.** That list is separate from the
redirect URIs, which receive authorization codes. `id_token_hint` is what says
whose list to consult; without it there is no list, so no redirect. The hint's
signature is not verified — the specification uses it only to identify the
client — which is safe because the registered list is the boundary: a forged
hint can only reach URIs the named client itself registered.

An unregistered destination is dropped rather than refused. The user asked to
be logged out and they are; they simply stay here.

Logging out here does not reach into other applications and end their sessions.
Each keeps its own, and each has to log its own user out. What that costs you
is that a user who signs out of one application is not signed out of the
others; they will simply not be asked to authenticate again the next time one
sends them over. Back-channel logout is not implemented.

Administrators can end sessions and revoke tokens for real: one at a time from
the **Sessions** page, everything a user holds with **Sign out everywhere** on
their page, or everything one application holds with **Revoke issued tokens**
on its page. Disabling a user does both for them.
