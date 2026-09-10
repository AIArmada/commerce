<?php

declare(strict_types=1);

$tablePrefix = env('MODERATION_TABLE_PREFIX', 'moderation_');

return [

    /* Database */
    'database' => [
        'table_prefix' => $tablePrefix,
        'json_column_type' => env('MODERATION_JSON_COLUMN_TYPE', 'jsonb'),

        'tables' => [
            'blocks' => env('MODERATION_TABLE_BLOCKS', $tablePrefix . 'blocks'),
            'moderation_actions' => env('MODERATION_TABLE_ACTIONS', $tablePrefix . 'actions'),
        ],
    ],

    /* Ownership */
    'owner' => [
        'enabled' => (bool) env('MODERATION_OWNER_ENABLED', true),
        'include_global' => (bool) env('MODERATION_OWNER_INCLUDE_GLOBAL', false),
        'auto_assign_on_create' => (bool) env('MODERATION_OWNER_AUTO_ASSIGN_ON_CREATE', true),
    ],

    /* Defaults */
    'defaults' => [
        'block_duration_days' => (int) env('MODERATION_BLOCK_DURATION_DAYS', 30),
    ],

];
