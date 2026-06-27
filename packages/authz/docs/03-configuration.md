---
title: Authz Configuration
---

## Main Settings

```php
return [
    'database' => [
        'table_prefix' => '',
        'tables' => [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
            'scopes' => 'authz_scopes',
        ],
    ],
    'super_admin_role' => 'super_admin',
    'guards' => ['web'],
    'users' => [
        'email_column' => 'email',
    ],
    'wildcard_permissions' => true,
    'permissions' => [
        'separator' => '.',
        'case' => 'camel',
    ],
    'scopes' => [
        'enabled' => false,
        'auto_create' => true,
        'enforce' => true,
    ],
    'impersonate' => [
        'enabled' => true,
        'guard' => 'web',
    ],
];
```

Authz core settings previously stored under `filament-authz.*` now use `authz.*`.
