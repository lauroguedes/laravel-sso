---
title: 'Deployment'
description: 'Running it in production: the queue, the scheduler and the signing keys.'
order: 3
---

This is an ordinary Laravel application. Anything that runs Laravel in
production runs this — Laravel Cloud, Forge, a container, a plain VPS.

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

`storage:link` is what makes an uploaded logo or sign-in background reachable;
without it they resolve to nothing. `sso:install` deliberately does not do it
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

If you terminate TLS at a proxy, configure `TrustProxies` — otherwise Laravel
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
anyone can mint an ID Token that your applications will believe — the same
severity as leaking your database, and harder to notice.

On a platform with an ephemeral filesystem, generate the keys once and inject
them as `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` environment variables
rather than letting each instance generate its own. Instances with different
keys will sign tokens that the others' published key set cannot verify.

### Rotating

Every ID Token carries a `kid` header naming the key that signed it, so clients
that cache the key set can follow a rotation. But this server publishes one key
at a time, so a rotation is a hard cutover:

1. Announce a window.
2. Replace the pair (`php artisan passport:keys --force`).
3. Restart, and clear caches.

Tokens signed by the old key are rejected from that moment. Rotate when you
have reason to — a suspected leak, a departing administrator with server
access — not on a routine schedule.

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

Run it under a supervisor that restarts it, and restart it on every deploy —
workers hold the old code in memory.

## Scheduler

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

It runs `activitylog:clean` daily, which enforces the audit retention setting
— `SSO_AUDIT_RETENTION_DAYS` is only its default. Without the scheduler the
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
anything with more than one application server, use MySQL or PostgreSQL —
SQLite cannot be shared across hosts.

Back it up. It holds your users, your applications' hashed secrets, your access
grants and your audit trail.

## Health

`/up` returns 200 when the framework boots. Point your monitor at
`/.well-known/openid-configuration` as well: it exercises the configuration
your relying parties actually depend on, and it is the first thing to break
when the issuer is misconfigured.

## Scaling

The server is stateless apart from the database and the signing keys, so it
scales horizontally as long as every instance has:

- the same signing keys,
- the same `SSO_ISSUER`,
- a shared session store,
- a shared cache.

Rate limits are counted per instance unless the cache is shared.
