---
title: 'Contributing'
description: 'Getting the project running locally, and the checks a change has to pass.'
order: 1
---

## Setting up

```bash
composer setup
php artisan sso:install
php artisan db:seed --class=SsoDemoSeeder
```

`composer setup` installs both dependency sets, copies `.env`, generates the
key, migrates and builds the front end. `sso:install` adds the OAuth signing
keys and the platform roles. The demo seeder gives you something to look at.

Then:

```bash
composer dev
```

That runs the application, the queue, the log tail and Vite together.

## Checks

```bash
composer ci:check
```

This is what has to pass. It runs, in order:

| Step             | Command                               |
| ---------------- | ------------------------------------- |
| Formatting, lint | `npm run check` (Prettier and ESLint) |
| Front-end types  | `npm run types:check` (`vue-tsc`)     |
| PHP formatting   | `pint --test`                         |
| Static analysis  | `phpstan` at level 7                  |
| Tests            | `php artisan test`                    |

CI also runs the suite on MySQL and PostgreSQL, and installs without dev
packages to run what a server runs. SQLite and a full development install each
hide problems a server would show.

Run the narrowest thing while you work:

```bash
php artisan test --compact tests/Feature/Admin
php artisan test --filter="a steward"
vendor/bin/pint --dirty
```

## Conventions

**Every change carries a test.** Feature tests by default, and unit tests only
for logic that does not touch the framework.

**Authorization goes through a policy**, and the ability is checked in the
form request as well as the controller. A form request validates before the
action runs, so a check only in the controller answers an unauthorized caller
with the shape of the form.

**Nothing sensitive reaches the audit trail.** Write through
`App\Services\AuditLogger`, never `activity()` or the `LogsActivity` trait.

**Front-end routes come from Wayfinder.** Regenerate with
`php artisan wayfinder:generate --with-form`. Omitting the flag drops the
`.form()` variants and produces spurious type errors.

**Components are imported explicitly.** A component used in a template but not
imported renders as an unknown element rather than failing, so `tests/Unit`
guards against it.

## Adding a setting

1. A default in `config('sso.defaults')`, and an entry in `$pinnable` if it
   should be fixable through the environment.
2. A rule in the right section of `ApplicationSettingsRequest::sections()`.
3. A field on the matching tab of `resources/js/pages/settings/Application.vue`.

No migration is needed, because the settings table is key and JSON. If the
setting has to reach a package, copy it in `SettingsServiceProvider` **and
test the key its consumer reads**, not the stored row.
