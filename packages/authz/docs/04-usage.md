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
