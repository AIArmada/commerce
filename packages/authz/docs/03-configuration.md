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
        'name_column' => 'name',
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
The `authz.permissions.case` value must be one of `snake`, `kebab`, `camel`,
`pascal`, `upper_snake`, or `lower`; unknown values throw during boot.
When `authz.scopes.enabled` is true, `permission.teams` must also be enabled;
the provider rejects that incompatible configuration during boot.

## Team Resolver

When `authz.scopes.enabled` is true, Authz sets `permission.team_resolver` to
its scope resolver so Spatie routes team ids through `AuthzScope` records. An
explicit host-provided `permission.team_resolver` is never overwritten. Do not
point `permission.team_resolver` at Spatie's default resolver while scopes are
enabled, or team-scoped role lookups will silently miss.

## Session Guard

Authz extends the `session` auth driver for every guard so impersonation can
switch identities quietly. Host applications with a custom `session` driver
must register their driver after the Authz service provider, or the Authz
guard will replace it.

## Leave-Impersonation Route

Authz registers `POST authz/leave-impersonation` (route name
`authz.leave-impersonation`) behind the `web` and `auth` middleware. It ends
the impersonation session and redirects to the sanitized back-to URL.
