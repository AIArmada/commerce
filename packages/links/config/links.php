<?php

declare(strict_types=1);

return [
    /* Database */
    'database' => [
        'table_prefix' => '',
        'json_column_type' => env('LINKS_JSON_COLUMN_TYPE', 'jsonb'),
        'tables' => [
            'links' => 'tracked_links',
            'clicks' => 'tracked_link_clicks',
        ],
    ],

    /* Defaults */
    'defaults' => [
        'slug_length' => 7,
        'slug_alphabet' => 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789',
        'redirect_status' => 302,
    ],

    /* Owner */
    'owner' => [
        'enabled' => false,
        'include_global' => false,
        'auto_assign_on_create' => true,
    ],

    /* Features / Behavior */
    'features' => [
        'tracking' => [
            'ip' => [
                'enabled' => true,
                'anonymize' => false,
            ],
            'user_agent' => [
                'enabled' => true,
                'store_raw' => true,
            ],
            'bots' => [
                'record' => true,
                'count' => false,
            ],
        ],
        'security' => [
            'require_https' => true,
            'allowed_hosts' => array_filter(explode(',', (string) env('LINKS_ALLOWED_HOSTS', ''))),
            'reserved_slugs' => [
                'admin',
                'api',
                'app',
                'dashboard',
                'go',
                'links',
                'login',
                'logout',
                'register',
            ],
        ],
        'retention' => [
            'prune_clicks_after_days' => 365,
        ],
    ],

    /* HTTP */
    'routing' => [
        'prefix' => 'go',
        'domain' => null,
        'middleware' => ['web'],
        'name' => 'links.redirect',
    ],
];
