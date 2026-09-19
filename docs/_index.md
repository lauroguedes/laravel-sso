---
title: 'Overview'
description: 'A self-hosted OpenID Connect provider you can read in an afternoon.'
order: 1
---

Laravel SSO is an OpenID Connect provider you run yourself. Register your
applications in a web interface, and your people sign in to all of them with
one account.

Applications connect with any standard OpenID Connect client library. Nothing
on the other end has to be Laravel, or PHP.

## What it does

- **OAuth 2.0:** authorization code with PKCE, refresh tokens, and client
  credentials for machine-to-machine access.
- **OpenID Connect:** discovery, a published key set, ID Tokens, UserInfo,
  introspection, revocation and RP-initiated logout.
- **Users:** created by administrators, with password reset, two-factor
  authentication, passkeys and email verification.
- **Applications:** each with its own credentials, redirect URIs and scopes.
- **Per-application roles:** every application defines its own, and receives
  the ones its users hold in its tokens.
- **Sessions and an audit trail:** every session and token, revocable. Who did
  what, from where, and when.

## What it is not

Not an enterprise IAM platform, and not trying to be. No LDAP bridge, no SAML,
no federation between providers. It is deliberately smaller and easier to read
than Keycloak: a Laravel application you can clone, configure, deploy and own.

## Where to start

| If you are                      | Start at                                           |
| ------------------------------- | -------------------------------------------------- |
| Standing a server up            | [Installation](/docs/getting-started/installation) |
| Running one                     | [The interface](/docs/administration/interface)    |
| Connecting an application to it | [Applications](/docs/integrating/applications)     |
| Working on the project itself   | [Development](/docs/development)                   |
| Upgrading to a new release      | [Changelog](/docs/changelog)                       |
