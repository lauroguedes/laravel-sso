---
title: 'Security'
description: 'What the server guarantees, what is yours to get right, and the known limits.'
order: 3
---

What this server guarantees, what it leaves to you, and what it deliberately
does not do.

## What it guarantees

**Passwords are never stored in a recoverable form.** They are hashed with
bcrypt, they are never written to a log, and they cannot be passed to
`sso:admin` as a command line argument, because arguments are readable in shell
history and in the process list.

**Client secrets are hashed.** A secret is shown once, at creation. It cannot
be shown again, only replaced.

**Nothing sensitive reaches the audit trail.** Every entry is written through
one place, which strips anything whose key looks like a credential (password,
secret, token, code, verifier, recovery code) at any depth of the recorded
data, matched without regard to case or separators. An audit record is
long-lived, widely readable and frequently exported, which makes it exactly
the wrong place for a credential.

**Redirect URIs are matched exactly.** No wildcards, at any setting. A wildcard
redirect is how authorization codes get delivered to somebody else's host.
Outside local development, they must also be HTTPS, with a carve-out for
loopback addresses so native and CLI clients remain developable.

**PKCE is required.** Public clients cannot opt out. Only `S256` is offered,
never `plain`.

**The implicit grant and the resource owner password credentials grant do not
exist here, and must not be added.** The first returns tokens in a URL
fragment, where they land in browser history and referrer headers. The second
requires every application to handle the user's password, the thing this
server exists to prevent.

**Per-application authorization is isolated.** The `roles` and `permissions`
claims are computed for the client that asked. One application never learns
what a user may do in another, through any endpoint.

**Disabling a user revokes everything they hold.** Every browser session ends
and every token any application holds for them is revoked, in the same action.
A disabled account has no live credentials anywhere.

**Post-logout redirects must be registered, and are matched exactly.** They
use their own list, not the redirect URIs, which receive authorization codes.
Widening those to cover logout landing pages would be the wrong trade. An
unregistered destination is dropped and the user is left here, still logged
out.

**Accounts are never deleted through the interface.** There is no route for
it, including for your own account. Deleting a user would leave their audit
entries with no causer and silently drop their access grants. Administrators
disable accounts instead.

**Failures do not enumerate accounts.** A sign-in with an unknown address, a
wrong password, or a disabled account all fail identically.

**Protocol endpoints are rate limited** per IP, and sign-in attempts are
throttled by Fortify per address and IP together.

## What is yours to get right

**TLS everywhere.** This server hands out bearer tokens. Over plain HTTP, all
of the above is decoration. If you terminate TLS at a proxy, configure
`TrustProxies`.

**`APP_DEBUG=false` in production.** A stack trace here can carry client
identifiers, token payloads and authorization request parameters.

**The signing key.** Anyone holding `storage/oauth-private.key` (or
`PASSPORT_PRIVATE_KEY`) can mint an ID Token that all of your applications will
believe. Back it up encrypted, separately from the database, and restrict who
can read it on the server. See
[Deployment](/docs/getting-started/deployment#signing-keys).

**Who holds Super Admin.** The role carries every platform permission,
including the ability to register applications and read the audit trail. Grant
it narrowly, and review it: the trail records every grant.

**Who stewards which application.** The Developer role lets somebody
administer the applications you assign them, and nothing else. It is the
narrower thing to give an integrator, but it is still a delegation: a steward
chooses their application's redirect URIs, which is where its authorization
codes are delivered. Assign applications, not the role, to control the reach,
and withdraw the role to silence every assignment at once. See
[Authorization](/docs/administration/authorization#stewarding-an-application).

**The consent switch.** Turning off the consent screen for an application you
do not control means its users are never asked before it receives their
identity. Together with **Restrict access**, it is the pair that decides who
reaches an application at all, which is why both stay with administrators
rather than with the application's steward.

**Two-factor authentication for administrators.** It is available, as TOTP and
as passkeys, but it is not compulsory. If someone can administer this server,
they can register an application that receives everyone's identity.

## Known limitations

These are real, and stated here rather than left to be discovered.

**There is no single sign-out.** `/oauth/logout` ends the session on this
server only. Applications keep their own sessions and must end them
themselves. Back-channel logout is not implemented.

**No account lockout.** Sign-in attempts are throttled, but a targeted attack
across many addresses is slowed rather than stopped. Two-factor authentication
is the answer, not a lockout policy that a determined attacker can use to lock
your users out for you.

**The audit trail cannot be edited or deleted through the interface.** There
is no route that writes to it. It can be edited by anyone with database
access, so it is evidence of what happened, not proof against someone who
already owns the server. Ship it somewhere else if you need that.

## Reporting a problem

If you find a vulnerability, report it privately to the maintainer of your fork
rather than opening a public issue.
