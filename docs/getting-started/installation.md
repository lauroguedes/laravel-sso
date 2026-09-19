---
title: 'Installation'
description: 'Requirements, the installer, and creating your first administrator.'
order: 1
---

## Requirements

|            |                                                                            |
| ---------- | -------------------------------------------------------------------------- |
| PHP        | 8.3 or newer                                                               |
| Node       | 20.19+ or 22.12+, to build the interface with Vite 8                       |
| Database   | Anything Laravel 13 supports: SQLite, MySQL 8, MariaDB 10.6, PostgreSQL 13 |
| Extensions | the usual Laravel set, plus `openssl` for the signing keys                 |

A queue worker and a scheduler are needed in production, but not to try the
server out. See [Deployment](/docs/getting-started/deployment).

## Getting a server running

```bash
git clone <your fork> laravel-sso
cd laravel-sso

composer install
npm install && npm run build

cp .env.example .env
php artisan sso:install
```

Set `SSO_ISSUER` in `.env` before you run the installer if you already know the
public URL. It can be changed later, but every application you have registered
by then will need to be told about the change, so it is worth getting right
first. See [Configuration](/docs/getting-started/configuration).

## What `sso:install` does

Each step is skipped when it has already been done, so the command is safe to
run again: on a new checkout, after an upgrade, or just to check an existing
installation.

| Step                           | Behaviour                                                                                                                                                                                              |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Application key                | Generated if `APP_KEY` is empty. An existing key is never replaced.                                                                                                                                    |
| Migrations                     | Run. Pass `--skip-migrations` to leave the database alone.                                                                                                                                             |
| OAuth signing keys             | Generated if `storage/oauth-private.key` is absent and `PASSPORT_PRIVATE_KEY` is not set. **An existing key pair is never replaced**, because doing so would invalidate every ID Token already issued. |
| Platform roles and permissions | Reconciled with the enums in `app/Enums`: the Super Admin and Developer roles, and every permission.                                                                                                   |
| First administrator            | Offered if nobody holds the Super Admin role.                                                                                                                                                          |
| Report                         | Prints the issuer, the discovery URL and every protocol endpoint.                                                                                                                                      |

It ends with warnings for anything that looks wrong: an issuer that is not
HTTPS outside local development, an issuer that disagrees with `APP_URL`, a
missing `openid` scope, or `APP_DEBUG` left on in production. These are
warnings, not failures. Behind a terminating proxy some of them are expected.

## Creating administrators

The installer offers to create the first one. Afterwards, administrators are
created in the interface, or from the console:

```bash
php artisan sso:admin
```

The command asks for a name, an email address and a password. If the address
already belongs to a user, it offers to promote that user instead, leaving
their password alone.

The password is never accepted as a command line argument, because arguments
are readable in shell history and in the process list. When there is no
terminal to ask, in a container build for example, the command generates a
strong password and prints it once:

```bash
php artisan sso:admin --no-interaction --name="Ada Admin" --email=ada@example.com
```

An account created this way is marked as having a verified email address:
whoever ran the command already had shell access to the server, which is a
stronger claim on the account than receiving a message would be, and a fresh
self-hosted install often has no mail transport configured yet.

## Demo data

For development only:

```bash
php artisan db:seed --class=SsoDemoSeeder
```

This creates an administrator, a developer, three users (one of them
disabled), two applications (a confidential web application and a public
single page application) and a set of roles and permissions on the first of
them. Every account uses the password `secret`, so the seeder refuses to run
when the environment is production. It prints the accounts it created when it
finishes.

On a public demonstration the administrator gets a new password on every
reset instead. See [Deployment](/docs/getting-started/deployment#hosting-a-public-demo).

## Upgrading

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan sso:install --skip-migrations
```

The last line is optional. It reconciles the platform permissions with any
that a new version added, and reports the configuration back to you.
