<?php

declare(strict_types=1);

$tablePrefix = env('AUTHZ_TABLE_PREFIX', env('COMMERCE_TABLE_PREFIX', ''));

$tables = [
    'permission_groups' => $tablePrefix.'authz_permission_groups',
    'role_templates' => $tablePrefix.'authz_role_templates',
    'permission_group_permission' => $tablePrefix.'authz_permission_group_permission',
    'scoped_permissions' => $tablePrefix.'authz_scoped_permissions',
    'access_policies' => $tablePrefix.'authz_access_policies',
    'audit_logs' => $tablePrefix.'authz_audit_logs',
];

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => $tablePrefix,
        'json_column_type' => env('AUTHZ_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => $tables,
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
    'user_model' => App\Models\User::class,
    'panel_guard_map' => [],
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
        'permission_matrix' => true,
        'role_hierarchy' => true,
        'stats_widget' => true,
        'hierarchy_widget' => true,
        'activity_widget' => true,
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

    /*
    |--------------------------------------------------------------------------
    | Permission Discovery
    |--------------------------------------------------------------------------
    |
    | Auto-discover permissions from Filament resources implementing
    | RegistersPermissions interface.
    |
    */
    'discovery' => [
        'enabled' => env('AUTHZ_DISCOVERY_ENABLED', false),
        'auto_sync' => env('AUTHZ_DISCOVERY_AUTO_SYNC', false),
        'namespaces' => [
            // Add namespaces to scan for resources with permission registration
            // 'AIArmada\\FilamentVouchers\\Resources',
            // 'AIArmada\\FilamentCart\\Resources',
            // 'AIArmada\\FilamentInventory\\Resources',
        ],
    ],
];
