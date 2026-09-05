# Authorization

There are two separate systems here, and keeping them separate is the point.

**Platform permissions** decide who may administer _this server_. **Application
roles and permissions** describe what a user may do _inside a registered
application_, and are reported to that application in its tokens.

An administrator of this server is not thereby an administrator of every
application registered on it.

## Platform permissions

These govern the administration interface. There are seven:

| Permission                |                                                                   |
| ------------------------- | ----------------------------------------------------------------- |
| `sso.users.view`          | See users                                                         |
| `sso.users.manage`        | Create and edit users, disable them, end their sessions           |
| `sso.applications.view`   | See applications                                                  |
| `sso.applications.manage` | Register and edit applications, regenerate secrets, revoke tokens |
| `sso.roles.manage`        | Define application roles and permissions, and grant access        |
| `sso.audit.view`          | Read the audit trail                                              |
| `sso.settings.manage`     | Reserved for server settings                                      |

One role holds all of them: **Super Admin**. It is created by `sso:install` and
by `sso:admin`.

They are defined as enums in `app/Enums/PlatformPermission.php` and
`app/Enums/PlatformRole.php`, and reconciled into the database by the
`PlatformPermissionsSeeder`. Add a case, run the seeder, and it exists.
Permissions removed from the enum are deliberately left in the database, since
they may still be attached to a role somebody created.

A user with only `sso.users.view` can look at users and can do nothing to them.
Every action is authorized separately from every listing.

## Application roles and permissions

Each application defines its own, on its **Roles** page. They mean nothing
outside it: `Admin` in your reporting application is unrelated to `Admin` in
billing, and neither has any bearing on this server.

A **permission** is a capability the application recognises — `reports.view`,
`invoices.export`. A **role** is a named bundle of them.

**This server stores and reports permissions. It never enforces them.** The
application receiving them decides what they allow. That division is
deliberate: the identity provider should not need to understand your business
rules, and you should not have to redeploy it to change one.

## Granting access

On an application's **Access** page, you grant a user access and optionally
give them a role there. A user holds at most one role per application.

Two things follow from a grant:

**The `roles` claim.** If the application was granted the `roles` scope, its
tokens carry the role and the permissions that user holds _there_:

```json
{
    "sub": "42",
    "roles": ["Analyst"],
    "permissions": ["reports.view", "reports.export"]
}
```

Both are scoped to the client that asked. Reporting never learns what a user
may do in billing — not through an ID Token, not through UserInfo, not through
introspection.

**Admission, if the application restricts access.** With **Restrict access**
turned on, only users with a grant may sign in to that application at all.
Everyone else is refused at the authorization endpoint, before any code is
issued — they are not bounced back to the application with an error it might
mishandle.

With it off — the default — anyone who can sign in to this server can sign in
to the application. A grant then only affects what the token says, not whether
one is issued.

## Choosing between them

Use **restrict access** when the application is for a subset of your people:
an internal admin tool, a finance system.

Use **roles** when everyone may sign in but they should not all see the same
thing.

Use both when the application is both restricted and internally tiered. That is
the common case for anything worth restricting.

## Disabled users

Disabling a user withdraws their ability to authenticate, and everything that
follows from it: they cannot sign in, their two-factor challenge is refused
before it starts, every browser session ends, and every token any application
holds for them is revoked in the same action.

A disabled account therefore has no live credentials anywhere. Re-enabling it
does not restore the revoked tokens; applications obtain new ones through the
normal flow.

Use **Sign out everywhere** on its own when you want to end someone's sessions
without withdrawing their account — after a lost laptop, say.

Users are disabled rather than deleted so that the audit trail keeps naming
somebody real. There is no route for deleting an account, including your own.
