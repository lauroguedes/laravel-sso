# Applications

An application is an OAuth 2.0 client: something that sends users here to sign
in, or that calls an API as itself. Registering one is how it gets credentials.

## Registering one

**Applications → Add application.** A short sequence of steps asks for:

**Name and description.** Shown to users on the consent screen, so name it the
way they would recognise it.

**Type.** This decides the grant types and whether a client secret exists, and
it cannot be changed afterwards — changing it would silently break every
integration already using the client.

| Type                  | For                                                       | Flow                         | Secret |
| --------------------- | --------------------------------------------------------- | ---------------------------- | ------ |
| Web application       | Server-side applications that can keep a secret           | Authorization code           | Yes    |
| Single page or native | Browser and mobile applications that cannot keep a secret | Authorization code with PKCE | No     |
| Machine to machine    | Backend services acting as themselves, no user involved   | Client credentials           | Yes    |

**Redirect URIs.** Where the browser is sent back after signing in. Matched
exactly — no wildcards, no path prefixes, no trailing-slash forgiveness. Register
every URI the application actually uses, including the development one. Up to 20.

Machine-to-machine applications have no redirect URIs; nobody's browser is
involved.

**Post-logout redirect URIs.** Where this application may send the browser
after signing out. A separate list from the redirect URIs, deliberately: those
receive authorization codes, so you want as few of them as possible, and
widening them to cover a logout landing page would be the wrong trade. Matched
exactly, like redirect URIs.

Registering none is fine, and is the default. Signing out then ends the session
here and leaves the user on this server.

The type step decides what follows: a machine-to-machine client has no browser
to send anywhere, so it is not asked for URIs at all.

**Scopes.** What the application may ask for. See
[OpenID Connect](openid-connect.md#scopes-and-claims). Interactive applications
are pre-selected with `openid`, `profile` and `email`.

## Credentials

The client ID is shown on the application's page and is not a secret.

**The client secret is shown once, when the application is created.** It is
stored only as a hash, so it cannot be shown again — the same reason your own
password cannot be. Copy it into the consuming application's configuration at
once.

If it is lost, or leaked, **regenerate** it from the application's page. The
previous secret stops working immediately, so schedule this: the application
cannot obtain new tokens until it is redeployed with the new value. Tokens it
already holds keep working until they expire.

## Settings

Two switches on the application's page change how it behaves, and both start
off:

**Skip the consent screen.** Users are not asked to approve this application;
they are sent straight back to it. Reasonable for an application you operate
yourself, where "do you allow this?" is a question the user cannot meaningfully
answer. Never turn it on for an application somebody else controls.

**Restrict access.** Only users who have been explicitly granted access may
sign in to this application. Everyone else is refused at the authorization
endpoint, before a code is issued. See [Authorization](authorization.md).

## Disabling

Disabling an application revokes every token it has issued, immediately, and
refuses new authorization requests. Live tokens are revoked rather than left to
expire, because otherwise a disabled application would keep calling resource
servers for up to another fifteen minutes.

Re-enabling it does not restore those tokens; clients obtain new ones through
the normal flow.

Applications are disabled rather than deleted so that the audit trail keeps
naming something real.

## Revoking tokens

**Revoke issued tokens** on the application's page invalidates everything it
currently holds without taking it out of service. Use it after a suspected
leak: its users are sent back to sign in, and the application carries on
working.

Individual tokens are revoked from the **Sessions** page, which lists every
token this server has issued and which application holds it.

## Connecting the other end

Give the developer the issuer and the credentials:

```
Issuer         https://auth.example.com
Discovery      https://auth.example.com/.well-known/openid-configuration
Client ID      9f3c2b...
Client secret  (shown once at creation)
Redirect URI   https://app.example.com/auth/callback
```

Most libraries need only the issuer and the credentials — they read the
endpoints, the supported scopes and the signing keys from the discovery
document themselves.

Nothing about the consuming application has to be Laravel, or PHP. Any library
that implements OpenID Connect will work: `openid-client` for Node,
`mozilla-django-oidc` for Django, `AddOpenIdConnect` for ASP.NET, Spring
Security's OAuth2 client, and so on.

For the protocol details, and for what to do without a library, see
[OpenID Connect](openid-connect.md).

## History

Each application has its own audit page, listing everything that has happened
to it and to access grants on it: registration, edits, secret regeneration,
enabling and disabling, and every grant and revocation.
