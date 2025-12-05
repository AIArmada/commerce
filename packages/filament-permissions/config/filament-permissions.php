<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'json_column_type' => env('PERMISSIONS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => [
            'permission_groups' => 'perm_permission_groups',
            'role_templates' => 'perm_role_templates',
            'permission_group_permission' => 'perm_permission_group_permission',
            'scoped_permissions' => 'perm_scoped_permissions',
            'access_policies' => 'perm_access_policies',
            'audit_logs' => 'perm_audit_logs',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'group' => 'Settings',
        'sort' => 99,
        'icons' => [
            'roles' => 'heroicon-o-shield-check',
            'permissions' => 'heroicon-o-key',
            'users' => 'heroicon-o-users',
            'groups' => 'heroicon-o-folder',
            'templates' => 'heroicon-o-document-duplicate',
            'policies' => 'heroicon-o-document-text',
            'audit_logs' => 'heroicon-o-clipboard-document-list',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Guards
    |--------------------------------------------------------------------------
    */
    'guards' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'super_admin_role' => 'super_admin',
    'default_guard' => 'web',
    'cache_ttl' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'enable_user_resource' => false,
    'features' => [
        'permission_explorer' => false,
        'diff_widget' => false,
        'impersonation_banner' => false,
        'auto_panel_middleware' => false,
        'panel_role_authorization' => false,
        'permission_groups' => true,
        'role_templates' => true,
        'scoped_permissions' => true,
        'access_policies' => true,
        'audit_logging' => true,
        'wildcard_permissions' => true,
        'role_inheritance' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Hierarchies
    |--------------------------------------------------------------------------
    */
    'hierarchies' => [
        'max_role_depth' => 5,
        'max_group_depth' => 5,
        'cache_hierarchy' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'enabled' => true,
        'async' => true,
        'retention_days' => 90,
        'log_access_checks' => false,
        'exclude_events' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | ABAC Policies
    |--------------------------------------------------------------------------
    */
    'policies' => [
        'combining_algorithm' => 'deny_overrides',
        'cache_policies' => true,
        'cache_ttl' => 300,
    ],
];
