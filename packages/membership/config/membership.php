<?php

declare(strict_types=1);

return [
    'database' => [
        'tables' => [
            'applications' => env('MEMBERSHIP_TABLE_APPLICATIONS', 'membership_applications'),
            'invitations' => env('MEMBERSHIP_TABLE_INVITATIONS', 'membership_invitations'),
        ],
    ],

    'invitations' => [
        'token_length' => 64,
        'hash_tokens' => true,
        'default_expiry_days' => 14,
    ],

    'pivot' => [
        'table_suffix' => env('MEMBERSHIP_PIVOT_SUFFIX', '_members'),
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
        'team_scoped_roles' => env('MEMBERSHIP_TEAM_SCOPED', true),
        'owner' => [
            'enabled' => true,
            'include_global' => false,
            'auto_assign_on_create' => true,
            'owner_type_column' => 'owner_type',
            'owner_id_column' => 'owner_id',
        ],
    ],
];
