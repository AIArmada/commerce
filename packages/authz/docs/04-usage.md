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
