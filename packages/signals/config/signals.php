<?php

declare(strict_types=1);

$tablePrefix = 'signal_';

return [
    /* Database */
    'database' => [
        'table_prefix' => $tablePrefix,
        'json_column_type' => env('SIGNALS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => [
            'tracked_properties' => $tablePrefix . 'tracked_properties',
            'identities' => $tablePrefix . 'identities',
            'sessions' => $tablePrefix . 'sessions',
            'events' => $tablePrefix . 'events',
            'daily_metrics' => $tablePrefix . 'daily_metrics',
            'goals' => $tablePrefix . 'goals',
            'segments' => $tablePrefix . 'segments',
            'saved_reports' => $tablePrefix . 'saved_reports',
            'alert_rules' => $tablePrefix . 'alert_rules',
            'alert_logs' => $tablePrefix . 'alert_logs',
        ],
    ],

    /* Defaults */
    'defaults' => [
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'property_type' => 'website',
        'page_view_event_name' => 'page_view',
        'session_duration_seconds' => 1800,
    ],

    /* Features / Behavior */
    'features' => [
        'owner' => [
            'enabled' => true,
            'include_global' => false,
            'auto_assign_on_create' => true,
        ],
    ],

    /* Integrations */
    'integrations' => [
        'cart' => [
            'enabled' => true,
            'listen_for_item_added' => true,
            'listen_for_item_removed' => true,
            'listen_for_cleared' => true,
            'item_added_event_name' => 'cart.item.added',
            'item_removed_event_name' => 'cart.item.removed',
            'cleared_event_name' => 'cart.cleared',
            'event_category' => 'cart',
        ],
        'checkout' => [
            'enabled' => true,
            'listen_for_started' => true,
            'listen_for_completed' => true,
            'started_event_name' => 'checkout.started',
            'event_name' => 'checkout.completed',
            'event_category' => 'checkout',
        ],
        'orders' => [
            'enabled' => true,
            'listen_for_paid' => true,
            'event_name' => 'order.paid',
            'event_category' => 'conversion',
        ],
        'vouchers' => [
            'enabled' => true,
            'listen_for_applied' => true,
            'listen_for_removed' => true,
            'applied_event_name' => 'voucher.applied',
            'removed_event_name' => 'voucher.removed',
            'event_category' => 'promotion',
        ],
    ],

    /* HTTP */
    'http' => [
        'prefix' => 'api/signals',
        'middleware' => ['api'],
        'tracker_script' => 'tracker.js',
    ],
];