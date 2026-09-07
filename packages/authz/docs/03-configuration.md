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
        'guard' => 'web',
    ],
];
```

The `authz.guards` list is the shared default consumed by core commands and the Filament adapter. Every listed guard must exist in `config/auth.php`.

The `authz.permissions.separator` value must be exactly one non-alphanumeric
character. The service provider rejects invalid values during application boot.
When `authz.scopes.enabled` is true, `permission.teams` must also be enabled;
the provider rejects that incompatible configuration during boot.
