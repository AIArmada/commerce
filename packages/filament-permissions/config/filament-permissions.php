<?php

declare(strict_types=1);

return [
    'guards' => [
        'web',
        // add additional guards here e.g. 'admin'
    ],
    'user_model' => App\Models\User::class,
    'panel_guard_map' => [
        // 'admin-panel' => 'admin',
    ],
    // Panel-specific role access rules.
    // Each panel id may list roles permitted to access it. If empty, falls back to guard only.
    'panel_roles' => [
        'admin' => ['Super Admin', 'Admin'],
        'member' => ['Super Admin', 'Member'],
    ],
    'super_admin_role' => 'Super Admin',
    'enable_user_resource' => true,
    'navigation' => [
        'group' => 'Access Control',
        'sort' => 90,
        'icons' => [
            'roles' => 'heroicon-o-key',
            'permissions' => 'heroicon-o-shield-check',
            'users' => 'heroicon-o-user-group',
        ],
    ],
    'sync' => [
        // 'roles' => [ 'Admin' => ['users.viewAny','users.create','users.update','users.delete'] ],
        // 'permissions' => [ 'users.viewAny','users.view','users.create','users.update','users.delete' ],
    ],
    'permission_naming' => [
        'ability_to_permission' => AIArmada\FilamentPermissions\Support\DefaultAbilityToPermissionMapper::class,
    ],
    'features' => [
        'doctor' => true,
        'policy_generator' => true,
        'impersonation_banner' => true,
        'permission_explorer' => true,
        'diff_widget' => true,
        'export_import' => true,
        'auto_panel_middleware' => true,
        'panel_role_authorization' => true,
    ],
];
