<?php

declare(strict_types=1);

return [
    'navigation' => [
        'group' => 'Contacting',
        'sort' => 70,
        'icons' => [
            'contact_methods' => 'heroicon-o-phone',
            'social_profiles' => 'heroicon-o-share',
            'contact_snapshots' => 'heroicon-o-archive-box',
        ],
    ],

    'tables' => [
        'default_pagination' => 25,
        'show_owner_columns' => false,
        'show_verification_columns' => true,
        'show_visibility_columns' => true,
    ],

    'features' => [
        'standalone_resources' => false,
        'relation_managers' => true,
        'imports' => false,
        'exports' => true,
        'verification_badges' => true,
        'open_url_actions' => true,
    ],

    'resources' => [
        'contact_methods' => [
            'enabled' => false,
            'read_only' => false,
        ],
        'social_profiles' => [
            'enabled' => false,
            'read_only' => false,
        ],
        'contact_snapshots' => [
            'enabled' => false,
            'read_only' => true,
        ],
    ],
];
