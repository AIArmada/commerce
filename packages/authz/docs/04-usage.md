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
