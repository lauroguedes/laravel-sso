---
title: 'OpenID Connect'
description: 'The protocol surface in detail: endpoints, scopes, claims and flows.'
order: 2
---

This server implements OpenID Connect Core on top of OAuth 2.0. Any conforming
client library will work against it, and nothing here is Laravel-specific.

## Discovery

```
GET https://auth.example.com/.well-known/openid-configuration
```

**What it is.** A public JSON document at a fixed path under the issuer,
defined by OpenID Connect Discovery. It describes this server: every endpoint
it exposes and every option it supports.

**What it is for.** So a client is configured with one value, the issuer,
instead of eight separate URLs. When the server's configuration changes,
clients follow it on their own, without anyone editing them.

**How it works.** The client appends `/.well-known/openid-configuration` to the
issuer, fetches it once at startup and caches the result. Everything it needs
to run a sign-in comes out of that response:

```json
{
    "issuer": "https://auth.example.com",
    "authorization_endpoint": "https://auth.example.com/oauth/authorize",
    "token_endpoint": "https://auth.example.com/oauth/token",
    "userinfo_endpoint": "https://auth.example.com/oauth/userinfo",
    "jwks_uri": "https://auth.example.com/.well-known/jwks.json",
    "end_session_endpoint": "https://auth.example.com/oauth/logout",
    "introspection_endpoint": "https://auth.example.com/oauth/introspect",
    "revocation_endpoint": "https://auth.example.com/oauth/revoke",
    "response_types_supported": ["code"],
    "grant_types_supported": [
        "authorization_code",
        "refresh_token",
        "client_credentials"
    ],
    "scopes_supported": ["openid", "profile", "email", "roles"],
    "id_token_signing_alg_values_supported": ["RS256"],
    "code_challenge_methods_supported": ["S256"]
}
```

The `issuer` in the document must equal the value you configured the client
with, character for character. A client that finds anything else should stop:
that mismatch is how a token minted by one server ends up accepted by a client
that believes it is talking to another. Good libraries check this for you.

The document needs no credentials and reveals nothing private. It is rate
limited per IP by `SSO_RATE_LIMIT_DISCOVERY`.

## Client libraries

Point one of these at the issuer and it will read everything above for itself.
None of them need to know this server is Laravel.

| Language        | Library                                                                                                                                                      |
| --------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Node.js         | [openid-client](https://github.com/panva/openid-client)                                                                                                      |
| Browser and SPA | [oidc-client-ts](https://github.com/authts/oidc-client-ts)                                                                                                   |
| PHP             | [jumbojett/OpenID-Connect-PHP](https://github.com/jumbojett/OpenID-Connect-PHP)                                                                              |
| Laravel         | [Socialite](https://github.com/laravel/socialite) with an OpenID Connect provider                                                                            |
| Python          | [Authlib](https://authlib.org/)                                                                                                                              |
| Django          | [mozilla-django-oidc](https://github.com/mozilla/mozilla-django-oidc)                                                                                        |
| Java and Kotlin | [Spring Security OAuth2 Client](https://docs.spring.io/spring-security/reference/servlet/oauth2/client/index.html)                                           |
| .NET            | [Microsoft.AspNetCore.Authentication.OpenIdConnect](https://learn.microsoft.com/en-us/aspnet/core/security/authentication/configure-oidc-web-authentication) |
| Go              | [coreos/go-oidc](https://github.com/coreos/go-oidc)                                                                                                          |
| Ruby            | [omniauth_openid_connect](https://github.com/omniauth/omniauth_openid_connect)                                                                               |
| Rust            | [openidconnect](https://github.com/ramosbugs/openidconnect-rs)                                                                                               |
| iOS             | [AppAuth-iOS](https://github.com/openid/AppAuth-iOS)                                                                                                         |
| Android         | [AppAuth-Android](https://github.com/openid/AppAuth-Android)                                                                                                 |

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
where they end up in browser history and referrer headers. The second requires
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

Two optional parameters tie the sign-in to this request:

- `nonce=<random>` comes back in the ID Token, and only there. Send it to
  `/oauth/authorize`, not to the token endpoint.
- `max_age=<seconds>` asks for a recent sign-in. A user who signed in longer ago,
  or whose session was restored from a remember me cookie, signs in again first.
  With `prompt=none` nobody can be asked, so you get `error=login_required`.
  `prompt=login` always asks.

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

**3. Validate the ID Token.** Your library does this. If you are doing it by
hand, all of it is mandatory:

- The signature, against the key from `/.well-known/jwks.json` whose `kid`
  matches the token header.
- `iss` equals the issuer, exactly.
- `aud` contains your client ID.
- `exp` is in the future, `iat` is not implausibly old.
- `nonce` matches the one you sent, if you sent one.
- `auth_time` is recent enough, if you sent `max_age`.

An ID Token expires after its own lifetime, independently of the access token
issued beside it. See
[token lifetimes](/docs/getting-started/configuration#token-lifetimes).

An ID Token says who signed in. It is not an API credential, so do not send it
to your own backend as a bearer token, and do not accept one there.

## Refreshing

```bash
curl -X POST https://auth.example.com/oauth/token \
  -d grant_type=refresh_token \
  -d refresh_token=<the refresh token> \
  -d client_id=9f3c2b... \
  -d client_secret=<omit for a public client>
```

A refreshed ID Token keeps the `auth_time` of the original sign-in and carries
no `nonce`.

Access tokens last 15 minutes by default and refresh tokens 14 days. A refresh
token stops working the moment an administrator revokes it, from the
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
Use it when you want fresh values. The ID Token is a snapshot from sign-in
time.

The token must have been granted `openid`. One that was not gets `403` with
`insufficient_scope`, in the body and in the `WWW-Authenticate` header.

## The key set (`jwks.json`)

```
GET https://auth.example.com/.well-known/jwks.json
```

**What it is.** This server's public signing keys, published as a JSON Web Key
Set. `jwks_uri` in the discovery document points at it.

**What it is for.** Every ID Token is signed with a private key that never
leaves this server. Clients verify that signature with the matching public
half. Publishing the public half is what lets an application prove a token came
from here without holding any secret of its own and without calling back on
every request.

**How it works.** Each entry describes one public key: `kty` and `alg` say what
kind it is, `use` says it is for signatures, `n` and `e` are the RSA modulus and
exponent, and `kid` names it.

```json
{
    "keys": [
        {
            "kty": "RSA",
            "alg": "RS256",
            "use": "sig",
            "kid": "ab72ede7c6599d70",
            "n": "sKWwCflbnN7Dt--lnTzwj_wB3TDU...",
            "e": "AQAB"
        }
    ]
}
```

Every ID Token carries the same `kid` in its header, so a client holding
several cached keys knows which one to verify with. Clients fetch the set once,
cache it, and fetch it again when a token arrives naming a `kid` they do not
have. That is what lets a key rotation happen without redeploying anything.

Nothing here is secret. A public key verifies signatures and cannot create
them, which is why the set is safe to publish and safe to cache anywhere.

The private half lives at `storage/oauth-private.key`, or in
`PASSPORT_PRIVATE_KEY`. It is not in version control, and `sso:install` never
replaces an existing one. Rotating it invalidates every ID Token in flight and
every cached key set. See
[Deployment](/docs/getting-started/deployment#signing-keys).

## Introspection

```bash
curl -X POST https://auth.example.com/oauth/introspect \
  -u client_id:client_secret \
  -d token=<the token> \
  -d token_type_hint=access_token
```

**What it is.** The endpoint defined by RFC 7662, where a caller presents a
token and is told whether it is currently usable.

**What it is for.** Revocation. A token's expiry is fixed when it is issued, so
checking a signature and an `exp` locally cannot tell you the token was revoked
five minutes ago. This server is the only place that knows, so a resource
server that cannot afford to honour a revoked token for the rest of its
15-minute life asks here instead of trusting the expiry.

**How it works.** POST the token, authenticating as a confidential client with
`client_secret_basic` or `client_secret_post`. A public client, which holds no
secret, is refused with `invalid_client`, because the answer describes whoever
the token belongs to. A live token gets its metadata back:

```json
{
    "active": true,
    "scope": "openid profile email",
    "client_id": "9f3c2b...",
    "username": "ada@example.com",
    "token_type": "Bearer",
    "sub": "42",
    "aud": "9f3c2b...",
    "iss": "https://auth.example.com",
    "iat": 1757462400,
    "exp": 1757463300
}
```

`username` appears only when the token was granted the `email` scope.

Anything expired, revoked, forged or unrecognised gets `{"active": false}` and
nothing else, so the endpoint cannot be used to probe for which tokens exist. A
token counts only once its signature, or for a refresh token its encryption,
shows this server issued it.

Refresh tokens introspect too, with `token_type` set to `refresh_token`.
`token_type_hint` only decides which kind is tried first, and an unknown hint is
ignored.

> [!NOTE]
> A client can introspect any token, including one issued to a different
> application. That is what lets a resource server check the tokens of the
> clients that call it, so treat every confidential client as trusted.

## Revocation

```bash
curl -X POST https://auth.example.com/oauth/revoke \
  -u client_id:client_secret \
  -d token=<the token>
```

RFC 7009. A client retires a token it no longer needs, typically at sign-out.
Revoking either token of a pair revokes both.

A client can only revoke its own tokens. A public client revokes them by sending
its `client_id` without a secret. An unknown token, or one issued to another
client, still gets `200`, so the endpoint reveals nothing about tokens that are
not the caller's.

This is the client's own housekeeping. An administrator revokes tokens from the
interface instead, which is described under [Logout](#logout).

## Logout

```
GET /oauth/logout
  ?id_token_hint=<the ID Token>
  &post_logout_redirect_uri=https://app.example.com/signed-out
  &state=<echoed back to the landing page>
```

The same parameters can be sent by `POST`, from a form on your own pages.

**Send `id_token_hint`.** An ID Token this server signed, naming the user who
is signed in here, is what lets the user be signed out straight away. An
expired one still counts. Without it, or with a hint that does not check out,
the user is asked to confirm first, because a link anybody can send should not
end a session on its own.

`post_logout_redirect_uri` must be **registered on the application, in its
post-logout list, and is matched exactly.** That list is separate from the
redirect URIs, which receive authorization codes. The application is the one the
hint was issued to, or the one named by `client_id` if you send that instead.
When both are sent they must agree. An unregistered destination is dropped
rather than refused: the user still signs out, and simply stays here.

Logging out here does not reach into other applications and end their sessions.
Each keeps its own, and each has to log its own user out. What that costs you
is that a user who signs out of one application is not signed out of the
others, though they will not be asked to authenticate again the next time one
sends them over. Back-channel logout is not implemented.

Administrators can end sessions and revoke tokens for real: one at a time from
the **Sessions** page, everything a user holds with **Sign out everywhere** on
their page, or everything one application holds with **Revoke issued tokens**
on its page. Disabling a user does both for them.
