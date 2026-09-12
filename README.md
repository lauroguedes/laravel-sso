<div align="center">
<img src="public/favicon.svg" width="72" alt="Laravel SSO">

# Laravel SSO

A self-hosted OpenID Connect provider.<br>
Register your applications, and sign in to all of them with one account.

[![Laravel](https://img.shields.io/badge/Laravel-13.x-red?style=flat&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3+-777bb4?style=flat&logo=php&logoColor=white?style=flat)](https://php.net)
[![Inertia.js](https://img.shields.io/badge/Inertia.js-3.x-9553E9?style=flat&logo=inertia&logoColor=white)](https://inertiajs.com/)
[![Vue 3](https://img.shields.io/badge/Vue-3.x-4FC08D?style=flat&logo=vue.js&logoColor=white)](https://vuejs.org/)
[![Pest](https://img.shields.io/badge/Pest-5.x-8b5cf6?style=flat)](https://pestphp.com)
<br>
[![Packagist Version](https://img.shields.io/packagist/v/lauroguedes/laravel-sso?style=flat)](https://packagist.org/packages/lauroguedes/laravel-sso)
[![Packagist Downloads](https://img.shields.io/packagist/dt/lauroguedes/laravel-sso?style=flat)](https://packagist.org/packages/lauroguedes/laravel-sso)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat)](LICENSE)
<br>
[![CI](https://img.shields.io/github/actions/workflow/status/lauroguedes/laravel-sso/ci.yml?branch=main&label=ci&style=flat)](https://github.com/lauroguedes/laravel-sso/actions/workflows/ci.yml)
[![Laravel Forge Site Deployment Status](https://img.shields.io/endpoint?url=https%3A%2F%2Fforge.laravel.com%2Fsite-badges%2F999174fb-92fc-45df-8b37-a69147b9e906&style=plastic)](https://forge.laravel.com/lauro-guedes-q58/graceful-silence-fzg/3378928)

</div>

---

Run your own identity provider. Your people get one account and one sign-in page. Your applications get standard OpenID Connect and never see a password.

Applications connect with any [client library](https://laravel-sso.lauroguedes.dev/docs/integrating/openid-connect#client-libraries). Nothing on the other end has to be Laravel, or PHP.

It is deliberately smaller and easier to read than Keycloak: a Laravel application you can clone, configure, deploy and own completely.

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

Want something to look at? See it running at
[laravel-sso.lauroguedes.dev](https://laravel-sso.lauroguedes.dev), or seed
your own copy:

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
architecture. Anyone signed in can read it, and on a public demo so can every
visitor. Read it online at
[laravel-sso.lauroguedes.dev/docs](https://laravel-sso.lauroguedes.dev/docs).

## Tests

```bash
composer ci:check
```

Formatting, lint, front-end types, Pint, PHPStan level 7 and the test suite.

## Requirements

PHP 8.3+, Node 20.19+ or 22.12+, and SQLite, MySQL, MariaDB or PostgreSQL.

## Contributing

Issues and pull requests are welcome. Every change carries a test, and
`composer ci:check` has to pass. See the
[contributing guide](https://laravel-sso.lauroguedes.dev/docs/development/contributing).

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
