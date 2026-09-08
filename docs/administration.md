# Administration

What an administrator sees, and what each part of the interface does. Who may
reach any of it is decided by the platform permissions in
[Authorization](authorization.md).

## Dashboard

Counts of what is currently in service — applications, users, disabled users,
browser sessions and issued tokens — each linking to the page behind it, plus
the most recent security and administration activity.

Recent activity is only shown to administrators who may read the audit trail.
Everyone else sees a note saying so, rather than an empty panel.

## Users

Accounts that can authenticate through this server. Search matches names and
addresses.

Every listing sorts by clicking a column heading, narrows through the
**Filters** menu, hides columns it does not need through **Columns**, and shows
15 rows at a time unless you choose otherwise at the foot of the table. Sorting
and filtering are applied by the database, so they cover the whole table rather
than the page on screen.

Times are shown as `d/m/Y H:i:s` throughout, in the reader's own timezone.

### Creating one

**Users → Add user.** Name, address, password, and optionally the platform
roles the account should hold.

**Email address is verified** marks the address as already confirmed. Use it
when you have another reason to trust the address — you created the account for
a colleague sitting next to you — and leave it off to make the user confirm it
themselves. Changing someone's address later clears the verification, because
the old confirmation said nothing about the new address.

Addresses must be lowercase, everywhere: an address is this server's subject
identity, and on some databases `Ada@example.com` and `ada@example.com` can
both exist and be issued tokens as different people.

### Editing one

Name, address, password and platform roles. Leaving the password blank keeps
the existing one, so editing a name is never a credential reset.

The page also lists the applications this user may sign in to and the role they
hold in each. That list is read only — access is granted from the application's
own page, which is where the roles it defines are listed.

### Withdrawing access

Two separate things, and you usually want both:

**Disable** withdraws the account. They cannot sign in, their two-factor
challenge is refused before it starts, every browser session ends, and every
token any application holds for them is revoked in the same action. Their
account and history are kept.

**Sign out everywhere** does the session and token half without withdrawing the
account — for a lost laptop, or a token you think has leaked. They can sign
straight back in.

Re-enabling an account does not restore revoked tokens; applications obtain new
ones through the normal flow.

Administrators cannot disable themselves; that would lock them out mid-session
and can leave a deployment with no reachable administrator.

Users are disabled rather than deleted so the audit trail keeps naming somebody
real. There is no route for deleting an account, including your own.

## Applications

Covered in full in [Applications](applications.md): registering clients, their
credentials, redirect URIs, scopes, consent, access restriction, and each
application's own roles, permissions, access list and history.

Each application has four sections, listed down the left of its pages:
**Overview** (credentials and configuration), **Roles**, **Access**, and
**Audit**.

## Sessions

Everything that currently keeps someone signed in, in two lists.

**Browser sessions** — sessions on this server, with the address and last
activity. Ending one signs that person out here. It does not touch tokens
applications already hold.

**Issued tokens** — every live access token, which application holds it, its
scopes and when it expires. Revoking one also revokes the refresh token issued
with it, so the application cannot renew it.

Both need `sso.users.manage`; `sso.users.view` shows the page without the
controls.

This page reads the session table directly, so it needs
`SESSION_DRIVER=database`. Under any other driver it says so and lists no
browser sessions — tokens are still listed, since those live in the database
regardless.

## Audit

What has been done on this server, and by whom. Newest first, filterable by
stream and searchable.

Two streams:

| Stream         | Records                                                                                                                              |
| -------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| Security       | Sign-ins, failed sign-ins, sign-outs, users being disabled, sessions and tokens revoked                                              |
| Administration | Users and applications created, updated, enabled and disabled; client secrets regenerated; access granted and revoked; roles changed |

Each entry records the event, who caused it, which application it concerned,
the address it came from and when. An action taken from the console records no
address, rather than inventing one.

Search matches an address exactly, an event by prefix, and otherwise the
description.

Every application also has its own audit page, listing only what concerns it.

**The trail is read only.** There is no route that edits or deletes an entry.
Retention is the scheduled `activitylog:clean` command's job — see
`SSO_AUDIT_RETENTION_DAYS` in [Configuration](configuration.md#audit-trail).
Anyone with database access can still edit it directly, so ship it elsewhere if
you need it to be evidence against someone who owns the server.

## What a member sees

Someone with no platform permission sees only a dashboard describing their own
account: the applications they may sign in to, the role and permissions they
hold in each, and when they last signed in. Nothing on it describes anybody
else, and the sections they cannot open are not offered in the menu.

A page they may not open, or one that does not exist, sends them back to that
dashboard with an explanation rather than replacing the interface with an error
page. A problem with the request is amber; a fault on this server is red.

## Your own account

Under **Settings**, and available to every signed-in user, not only
administrators.

**Profile** — name and email address. Changing the address requires confirming
it again when verification is enabled.

**Security** — change your password, and set up two-factor authentication
(an authenticator app, with recovery codes) or a passkey. Neither is
compulsory; both are strongly worth enabling on any account that can administer
this server.

Light, dark and follow-the-system are not here: they are a per-device
preference, so the switch lives in the top right of every page instead.

There is deliberately no way to delete your own account. Deleting a user would
leave their audit entries with no causer and drop their access grants without
an administrator ever seeing it; ask an administrator to disable it instead.
