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
        'admin' => env('MEMBERSHIP_ROLE_ADMIN_NAME', 'admin'),
        'editor' => env('MEMBERSHIP_ROLE_EDITOR_NAME', 'editor'),
        'viewer' => env('MEMBERSHIP_ROLE_VIEWER_NAME', 'viewer'),
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

Keep `hash_tokens` enabled in production.
