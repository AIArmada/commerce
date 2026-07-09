---
title: Membership Configuration
---

## Main Settings

```php
return [
    'database' => [
        'tables' => [
            'applications' => 'membership_applications',
            'invitations' => 'membership_invitations',
        ],
        'json_column_type' => 'jsonb',
    ],
    'invitations' => [
        'token_length' => 64,
        'hash_tokens' => true,
        'default_expiry_days' => 14,
    ],
    'pivot' => [
        'table_suffix' => '_members',
    ],
    'role_mapping' => [
        'owner' => env('MEMBERSHIP_ROLE_OWNER_NAME', 'owner'),
        'admin' => env('MEMBERSHIP_ROLE_ADMIN_NAME', 'admin'),
        'editor' => env('MEMBERSHIP_ROLE_EDITOR_NAME', 'editor'),
        'viewer' => env('MEMBERSHIP_ROLE_VIEWER_NAME', 'viewer'),
    ],
    'role_permissions' => [
        'owner' => ['*'],
        'admin' => ['update', 'manage-members'],
        'editor' => ['update'],
        'viewer' => ['view'],
    ],
    'features' => [
        'team_scoped_roles' => true,
        'owner' => [
            'enabled' => true,
            'include_global' => false,
            'auto_assign_on_create' => true,
            'owner_type_column' => 'owner_type',
            'owner_id_column' => 'owner_id',
        ],
    ],
];
```

## Role mapping

- `role_mapping` maps each `MemberRole` case (`owner`, `admin`, `editor`, `viewer`) to a Spatie role name
- Override via env when the host app already uses different role names

## Role permissions

- `role_permissions` lists permission names granted when `MembershipRoleSyncService` ensures a role
- `owner => ['*']` expands to every permission registered for the default guard
- Non-wildcard entries are prefixed with the subject class basename in snake case (for example `Team` → `team.update`) when a subject is available
- Apps should override `role_permissions` rather than hard-coding host permission names in the package

## Notes

- Keep `hash_tokens` enabled in production. The plaintext token is available only on `MembershipInvitationSent`.
- When owner scoping is enabled, resolve the current owner before reading or mutating applications and invitations.
