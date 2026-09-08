# Laravel SSO

A Laravel-first, self-hosted OpenID Connect provider.

Run it on your own infrastructure, register your applications in a web
interface, and sign in to all of them with one account. Applications connect
with any standard OpenID Connect client library — nothing on the other end has
to be written in Laravel, or in PHP.

## What it provides

- **OAuth 2.0** — authorization code with PKCE, refresh tokens, and client
  credentials for machine-to-machine access.
- **OpenID Connect** — discovery, a published key set, ID Tokens, UserInfo,
  token introspection, revocation and RP-initiated logout.
- **Users** — created by administrators, with password reset, two-factor
  authentication, passkeys and email verification from Laravel Fortify.
- **Applications** — registered through the interface, each with its own
  credentials, redirect URIs and scopes.
- **Application-scoped roles and permissions** — every application defines its
  own, and receives the ones its users hold in its tokens.
- **Sessions** — every browser session and issued token, revocable one at a
  time or all at once.
- **Audit log** — who did what, from where, and when.
- **Delegation** — a Developer role that lets an integrator look after the
  applications you assign them, and nothing else.
- **Settings** — the brand, the palette, the layout, token lifetimes and
  retention, all changed from the interface, or pinned through the environment
  by a deployment that manages its own configuration.

## What it is not

This is not an enterprise IAM platform, and does not try to be one. There is no
LDAP bridge, no SAML, no federation between providers, no user-facing
self-service portal. It is deliberately smaller and easier to read than
Keycloak: a Laravel application you can clone, configure, deploy and own
completely.

## Requirements

PHP 8.3 or newer, Composer, Node 20.19+ or 22.12+ (what Vite 8 needs to
build the interface), and a database — SQLite, MySQL, MariaDB or PostgreSQL.

## Quick start

```bash
git clone <your fork> laravel-sso
cd laravel-sso
composer install
npm install && npm run build
cp .env.example .env
php artisan sso:install
```

`sso:install` generates the application key, runs the migrations, creates the
OAuth signing keys, seeds the platform roles, offers to create your first
administrator, and prints the issuer and endpoints your applications will need.
It is safe to run again.

Then open the server and sign in — the root is the sign-in page — and register
an application.

For something to look at while exploring:

```bash
php artisan db:seed --class=SsoDemoSeeder
```

## Connecting an application

Register the application, then hand its developer five values:

|               |                                                             |
| ------------- | ----------------------------------------------------------- |
| Issuer        | `https://auth.example.com`                                  |
| Discovery     | `https://auth.example.com/.well-known/openid-configuration` |
| Client ID     | shown on the application's page                             |
| Client secret | shown once, when the application is created                 |
| Redirect URI  | whatever you registered, matched exactly                    |

Most client libraries need only the issuer: they read everything else from the
discovery document.

## Documentation

- [Installation](docs/installation.md) — getting a server running
- [Configuration](docs/configuration.md) — every setting, and what it changes
- [Administration](docs/administration.md) — the interface, users, sessions and the audit trail
- [Applications](docs/applications.md) — registering and integrating clients
- [OpenID Connect](docs/openid-connect.md) — the protocol surface in detail
- [Authorization](docs/authorization.md) — roles, permissions and access control
- [Deployment](docs/deployment.md) — running it in production
- [Security](docs/security.md) — the guarantees, and your responsibilities

## Development

```bash
composer run dev      # the application, the queue, logs and Vite
composer run ci:check # formatting, static analysis, types and tests
```

## Licence

MIT, as declared in `composer.json`. Add a `LICENSE` file to your fork if you
intend to redistribute it.
