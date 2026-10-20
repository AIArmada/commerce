---
title: Authz Usage
---

## Permission Keys

```php
use AIArmada\Authz\Facades\Authz;

$permission = Authz::buildPermissionKey('Order', 'viewAny');
```

## Scoped Checks

```php
use AIArmada\Authz\Facades\Authz;

$allowed = Authz::userCanInScope($user, 'orders.view', $team);
```

`$team` is an `AuthzScope`, a model that owns an `AuthzScope`, or an Authz
scope id when the Authz scope resolver is active; with the owner-context team
resolver it is an owner model or a raw owner key; with Spatie's default
resolver or a custom resolver it is a Spatie team id (persisted models resolve
to their key). Passing `null` checks the deliberate global scope, and
`Authz::userHasPermissionAcrossScopes($user, 'orders.view')` is the explicit
cross-scope surface.

The check fails closed: when a non-null scope cannot resolve, or the active
resolver cannot honor the requested team switch, `userCanInScope()` returns
`false` instead of checking global permissions. Run scoped work through
`withScope()` when you want the failure to surface instead:

```php
use AIArmada\Authz\Exceptions\ScopeResolutionException;
use AIArmada\Authz\Facades\Authz;

try {
    $result = Authz::withScope($team, fn (): bool => $user->can('orders.view'));
} catch (ScopeResolutionException $exception) {
    // The requested scope does not exist for the active team resolver.
}
```

`withScope()` restores the entry scope identity and override state on every
exit path, including nested scopes and throwing callbacks. A setup failure
that has already changed the resolver's state is rolled back to the entry
scope before the original error surfaces; if that rollback cannot be honored
either, `AIArmada\Authz\Exceptions\ScopeRestorationException` is thrown and the
service never continues under an unknown scope.

Only scope resolution and team-switch setup failures are classified as a
denied scope. An exception thrown by the ability or policy callback — even a
`ScopeResolutionException` — propagates unchanged in every resolver mode after
the previous team/owner/scope state is restored. A failed rollback is never
classified as a denied scope: it always surfaces as `ScopeRestorationException`
from both `withScope()` and `userCanInScope()`.

## Wildcard Permissions

When `authz.wildcard_permissions` is enabled, a granted permission containing
`*` authorizes every matching ability through the Gate:

- Every wildcard segment participates in the match. `orders.*.own` matches
  `orders.view.own` but not `orders.view.read.own`, and `*.view.*` matches
  `orders.view.own` and `orders.view.own.deep`.
- A pattern ending with the configured separator followed by `*` matches the
  whole trailing subtree: `orders.*` matches `orders.view` and
  `orders.view.own`.
- The configured `authz.permissions.separator` is honored, so a `:` separator
  matches `orders:*` against `orders:view`.
- Verdicts are read from the user's current scoped assignments on every check.
  Grants, revocations, role changes, role permission syncs, and super-admin
  role removals apply to the next Gate check — including on user instances
  that were loaded before the change and with permission teams enabled or
  disabled — without clearing any internal cache.

## Impersonation

```php
use AIArmada\Authz\Services\ImpersonateManager;

$manager = app(ImpersonateManager::class);
$manager->take($administrator, $target, 'web', '/admin');
```

`take()` enforces authorization by default: the impersonator must be allowed
to impersonate (via `canImpersonate()` or the super-admin role), the target
must allow it, self-impersonation is refused, and the tenant scope guard must
pass. Pass `$authorize: false` only when the caller already performed
equivalent checks.

## Console commands

```bash
# Sync roles and permissions declared in config('authz.sync')
php artisan authz:sync
php artisan authz:sync --flush-cache

# Assign the configured super-admin role (authz.super_admin_role)
php artisan authz:super-admin --user=<user-primary-key>
php artisan authz:super-admin --create
php artisan authz:super-admin --panel=<filament-panel-id>
```

`--user` takes a primary key. Omit it for an interactive email search, or pass
`--create` to create the user interactively. Both commands refuse to run when
`CommandProhibitor::prohibitDestructiveCommands()` has prohibited them (for
example in production).
