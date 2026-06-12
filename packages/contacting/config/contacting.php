<?php

declare(strict_types=1);

$tablePrefix = env('CONTACTING_TABLE_PREFIX', '');

return [
    'database' => [
        'table_prefix' => $tablePrefix,
        'json_column_type' => env('CONTACTING_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'jsonb')),
        'tables' => [
            'contact_methods' => env('CONTACTING_TABLE_CONTACT_METHODS', $tablePrefix . 'contact_methods'),
            'social_profiles' => env('CONTACTING_TABLE_SOCIAL_PROFILES', $tablePrefix . 'social_profiles'),
            'contact_snapshots' => env('CONTACTING_TABLE_CONTACT_SNAPSHOTS', $tablePrefix . 'contact_snapshots'),
        ],
    ],

    'defaults' => [
        'country_code' => env('CONTACTING_DEFAULT_COUNTRY_CODE', 'MY'),
        'public_by_default' => true,
        'verified_by_default' => false,
    ],

    'features' => [
        'owner' => [
            'enabled' => env('CONTACTING_OWNER_ENABLED', true),
            'include_global' => env('CONTACTING_OWNER_INCLUDE_GLOBAL', false),
            'auto_assign_on_create' => env('CONTACTING_OWNER_AUTO_ASSIGN', true),
        ],
        'contact_snapshots' => true,
        'strict_social_platforms' => false,
        'strict_contact_types' => false,
    ],

    'contact_methods' => [
        'types' => [
            'email',
            'phone',
            'mobile',
            'whatsapp',
            'website',
            'telegram',
            'fax',
            'other',
        ],

        'purposes' => [
            'general',
            'admin',
            'support',
            'billing',
            'sales',
            'registration',
            'media',
            'donation',
            'partnership',
            'emergency',
            'privacy',
            'other',
        ],
    ],

    'social_profiles' => [
        'platforms' => [
            'facebook',
            'instagram',
            'tiktok',
            'youtube',
            'linkedin',
            'x',
            'threads',
            'telegram',
            'telegram_channel',
            'telegram_group',
            'whatsapp_channel',
            'website',
            'other',
        ],
    ],
];