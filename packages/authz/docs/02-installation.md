---
title: Authz Installation
---

## Install

```bash
composer require aiarmada/authz
php artisan vendor:publish --tag=authz-config
php artisan migrate
```

Enable Spatie teams before migrating when scoped roles are required.

```php
// config/permission.php
'teams' => true,
```

## Requirements

Authz uses UUID key columns for roles, permissions, pivots, and scopes. Host
user models must use UUID (string) primary keys; integer-keyed models are not
supported.
