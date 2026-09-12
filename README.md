<p align="center">
  <img src="public/favicon.svg" width="72" alt="Laravel SSO">
</p>

<h1 align="center">Laravel SSO</h1>

<p align="center">
  A self-hosted OpenID Connect provider.<br>
  Register your applications, and sign in to all of them with one account.
</p>

<p align="center">
  <a href="https://github.com/lauroguedes/laravel-sso/actions/workflows/ci.yml"><img src="https://img.shields.io/github/actions/workflow/status/lauroguedes/laravel-sso/ci.yml?branch=main&label=ci&style=flat-square" alt="CI"></a>
  <a href="https://github.com/lauroguedes/laravel-sso/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue?style=flat-square" alt="MIT licence"></a>
  <img src="https://img.shields.io/badge/php-%5E8.3-777bb4?style=flat-square" alt="PHP ^8.3">
  <img src="https://img.shields.io/badge/laravel-13-ff2d20?style=flat-square" alt="Laravel 13">
  <a href="https://github.com/lauroguedes/laravel-sso/stargazers"><img src="https://img.shields.io/github/stars/lauroguedes/laravel-sso?style=flat-square" alt="Stars"></a>
</p>

---

Run your own identity provider. Your people get one account and one sign-in
page. Your applications get standard OpenID Connect and never see a password.

Applications connect with any client library: `openid-client`,
`mozilla-django-oidc`, Spring Security, ASP.NET. Nothing on the other end has
to be Laravel, or PHP.

It is deliberately smaller and easier to read than Keycloak: a Laravel
application you can clone, configure, deploy and own completely.

## Features

- **OAuth 2.0:** authorization code with PKCE, refresh tokens, client
  credentials.
- **OpenID Connect:** discovery, published key set, ID Tokens, UserInfo,
  introspection, revocation, RP-initiated logout.
- **Applications:** registered in the interface, each with its own
  credentials, redirect URIs and scopes. Secrets hashed, redirect URIs matched
  exactly.
- **Per-application roles:** every application defines its own, and its tokens
  carry the ones its users hold _there_. One application never learns about
  another.
- **Users:** password reset, two-factor authentication, passkeys, email
  verification.
- **Delegation:** a Developer role that hands somebody one application without
  handing them the server.
- **Sessions and audit trail:** every session and token revocable. Who did
  what, from where, and when.
- **Themeable:** brand, palette and layout changed from the interface, or
  pinned through the environment.

## Quick start

```bash
laravel new my-sso --using=lauroguedes/laravel-sso
```

Or clone it:

```bash
git clone https://github.com/lauroguedes/laravel-sso
cd laravel-sso
composer setup
php artisan sso:install
```

`sso:install` generates the signing keys, seeds the platform roles, offers to
create your first administrator, and prints the endpoints your applications
need. It is safe to run again.

Want something to look at?

```bash
php artisan db:seed --class=SsoDemoSeeder
```

## Connecting an application

Register it, then hand its developer the issuer and the credentials:

|               |                                                             |
| ------------- | ----------------------------------------------------------- |
| Issuer        | `https://auth.example.com`                                  |
| Discovery     | `https://auth.example.com/.well-known/openid-configuration` |
| Client ID     | shown on the application's page                             |
| Client secret | shown once, at creation                                     |

Most libraries need only the issuer. They read the rest from the discovery
document.

## Documentation

Full documentation ships with the server and is served from it at **`/docs`**:
installation, configuration, administration, integration, security and the
architecture.

## Tests

```bash
composer ci:check
```

Formatting, lint, front-end types, Pint, PHPStan level 7 and the test suite.

## Requirements

PHP 8.3+, Node 20.19+ or 22.12+, and SQLite, MySQL, MariaDB or PostgreSQL.

## Contributing

Issues and pull requests are welcome. Every change carries a test, and
`composer ci:check` has to pass. See `/docs/development/contributing`.

## Licence

MIT.

---

<p align="center">
  <b>If this is useful to you, please star the repository.</b><br>
  It is the thing that helps other people find it.
</p>

<p align="center">
  <a href="https://github.com/lauroguedes/laravel-sso/stargazers">⭐ Star this project</a>
  &nbsp;·&nbsp;
  <a href="https://buymeacoffee.com/lauroguedes">☕ Buy me a coffee</a>
</p>

<p align="center">
  <sub>Crafted by an Artisan ♥ <a href="https://lauroguedes.dev">Lauro Guedes</a></sub>
</p>
