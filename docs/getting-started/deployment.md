---
title: 'Deployment'
description: 'Running it in production: the queue, the scheduler and the signing keys.'
order: 3
---

This is an ordinary Laravel application. Anything that runs Laravel in
production runs this: Laravel Cloud, Forge, a container, a plain VPS.

Two things make it different from a typical application, and both are about
the fact that other systems depend on it: the **issuer must be stable**, and
the **signing keys must survive**.

## Before you go live

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force
php artisan sso:install --skip-migrations
php artisan storage:link

php artisan config:cache
php artisan route:cache
php artisan event:cache
```

`storage:link` is what makes an uploaded logo or sign-in background reachable.
Without it they resolve to nothing. `sso:install` deliberately does not do it
for you, since it writes outside the application's own directories.

Check the installer's output. It ends with warnings for the mistakes that are
easy to make and expensive to discover later.

## Environment

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://auth.example.com

SSO_ISSUER=https://auth.example.com
```

`APP_DEBUG=false` matters more here than in most applications: a stack trace
from this server can contain client identifiers, token payloads and query
parameters from an authorization request.

If you terminate TLS at a proxy, configure `TrustProxies`. Otherwise Laravel
generates `http://` URLs, the redirect back from `/oauth/authorize` is
downgraded, and the audit trail records your load balancer's address as every
user's IP.

## Signing keys

`storage/oauth-private.key` signs every ID Token. `storage/oauth-public.key` is
what relying parties fetch from `/.well-known/jwks.json` to verify one.

They are not in version control, and `sso:install` will never overwrite an
existing pair.

**Back them up, encrypted, separately from the database.** Losing the private
key means every application must re-verify against a new one. Leaking it means
anyone can mint an ID Token that your applications will believe, the same
severity as leaking your database and harder to notice.

On a platform with an ephemeral filesystem, every instance must read the same
key files. Generate the pair once, then put `storage/oauth-private.key` and
`storage/oauth-public.key` on a shared volume, or write them into `storage/`
from your secret store before `sso:install` runs. Otherwise the installer
generates a new pair for that instance, and instances with different keys sign
tokens that the others' published key set cannot verify.

> [!WARNING]
> Do not rely on `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` yet. Passport
> reads them for access tokens, but ID Tokens and the key set at
> `/.well-known/jwks.json` are only read from the files, so setting the
> variables alone leaves the two out of step.

### Rotating

Every ID Token carries a `kid` header naming the key that signed it, so clients
that cache the key set can follow a rotation. But this server publishes one key
at a time, so a rotation is a hard cutover:

1. Announce a window.
2. Replace the pair (`php artisan passport:keys --force`).
3. Restart, and clear caches.

Tokens signed by the old key are rejected from that moment. Rotate when you
have reason to, such as a suspected leak or a departing administrator with
server access, not on a routine schedule.

## Mail

Verification and password-reset messages are sent with Laravel's own
notifications, synchronously, on the request that triggers them. Configure a
real `MAIL_MAILER` before you enable email verification: with the default `log`
mailer the messages go to `storage/logs`, and a user who cannot verify their
address cannot sign in.

If you would rather not have a slow SMTP host stall a request, queue those
notifications and run a worker:

```bash
php artisan queue:work --tries=3
```

Run it under a supervisor that restarts it, and restart it on every deploy,
because workers hold the old code in memory.

## Scheduler

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

It runs `activitylog:clean` daily, which enforces the audit retention setting.
`SSO_AUDIT_RETENTION_DAYS` is only its default. Without the scheduler the
audit table grows forever, and it is the fastest-growing table in the schema,
gaining a row on every sign-in and every failed sign-in.

Add `passport:purge` to remove revoked and expired tokens if you want that
table bounded too.

## Sessions

`SESSION_DRIVER=database` is the default, and the **Sessions** page depends on
it: with any other driver the page reports that sessions are not stored where
it can see them, and administrators cannot end one. Redis is fine for
performance, at the cost of that page.

## Database

SQLite is fine for a small deployment and is what the defaults use. For
anything with more than one application server, use MySQL or PostgreSQL,
because SQLite cannot be shared across hosts.

Back it up. It holds your users, your applications' hashed secrets, your access
grants and your audit trail.

## Health

`/up` returns 200 when the framework boots. Point your monitor at
`/.well-known/openid-configuration` as well: it exercises the configuration
your relying parties actually depend on, and it is the first thing to break
when the issuer is misconfigured.

## Hosting a public demo

A demonstration server is signed into by strangers, and everything they can
reach they can also change. `sso:demo-reset` drops the database and rebuilds
the sample data, so what a visitor finds is the demo rather than whatever the
last visitor left behind.

```env
SSO_DEMO_MODE=true
SSO_DEMO_RESET_HOURS=6
```

With `SSO_DEMO_MODE` on, the scheduler runs the reset on that cycle. Run it by
hand with `php artisan sso:demo-reset`.

The same switch closes what a stranger could otherwise abuse:

| On a demo                    | What happens                                                                                 |
| ---------------------------- | -------------------------------------------------------------------------------------------- |
| Mail                         | Nothing is sent. The transport is replaced, so no message reaches an address a visitor typed |
| Email verification           | Forced off and pinned, so nobody is stranded behind a message that will never arrive         |
| The administrator's password | Regenerated on every reset, so what the last visitor wrote down stops working                |
| The sign-in page             | Fills that administrator in, since a demo nobody can enter is not a demo                     |

The published credentials live in `storage/app/private/demo-credentials.json`,
written only while `SSO_DEMO_MODE` is on and read back only while it still is.
The other demo accounts keep the seeder's shared password. `sso:demo-reset`
prints the new administrator password when it finishes.

> [!WARNING]
> This deletes every user, application and token. It refuses to run unless
> `SSO_DEMO_MODE` is on, and refuses in production regardless, because the
> seeder it runs creates accounts with a password anyone can look up. Never
> turn it on for an installation holding anything you want to keep.

## Scaling

The server is stateless apart from the database and the signing keys, so it
scales horizontally as long as every instance has:

- the same signing keys,
- the same `SSO_ISSUER`,
- a shared session store,
- a shared cache.

Rate limits are counted per instance unless the cache is shared.
