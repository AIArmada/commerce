<?php

declare(strict_types=1);

$tablePrefix = env('TICKETING_TABLE_PREFIX', 'ticket_');

return [
    'database' => [
        'table_prefix' => $tablePrefix,
        'json_column_type' => env('TICKETING_JSON_COLUMN_TYPE', 'jsonb'),
        'tables' => [
            'ticket_types' => env('TICKETING_TICKET_TYPES_TABLE', 'ticket_types'),
            'ticket_type_components' => env('TICKETING_TICKET_TYPE_COMPONENTS_TABLE', 'ticket_type_components'),
            'ticket_type_products' => env('TICKETING_TICKET_TYPE_PRODUCTS_TABLE', 'ticket_type_products'),
            'ticket_type_seating_options' => env('TICKETING_TICKET_TYPE_SEATING_OPTIONS_TABLE', 'ticket_type_seating_options'),
            'passes' => env('TICKETING_PASSES_TABLE', $tablePrefix . 'passes'),
            'pass_holders' => env('TICKETING_PASS_HOLDERS_TABLE', $tablePrefix . 'pass_holders'),
            'pass_transfers' => env('TICKETING_PASS_TRANSFERS_TABLE', $tablePrefix . 'pass_transfers'),
        ],
    ],
    'defaults' => [
        'currency' => env('TICKETING_CURRENCY', env('COMMERCE_CURRENCY', 'MYR')),
        'pass_no_prefix' => env('TICKETING_PASS_NO_PREFIX', 'PASS-'),
    ],
    'ticketable_types' => [],
    'allowed_ticketable_types' => [],
    'transfers' => [
        'bulk_max_size' => env('TICKETING_BULK_TRANSFER_MAX', 100),
        'expiry_grace_period' => env('TICKETING_TRANSFER_EXPIRY_GRACE', 0),
    ],
    'notifications' => [
        'ticket' => [
            'enabled' => true,
            'from_address' => env('TICKETING_FROM_ADDRESS'),
            'from_name' => env('TICKETING_FROM_NAME'),
        ],
    ],
    'features' => [
        'auto_issue_passes' => env('TICKETING_AUTO_ISSUE_PASSES', true),
    ],
    'owner' => [
        'enabled' => env('TICKETING_OWNER_ENABLED', true),
        'include_global' => false,
        'auto_assign_on_create' => env('TICKETING_OWNER_AUTO_ASSIGN', true),
    ],
    'events' => [
        'pricing_consistency_check' => env('TICKETING_PRICING_CONSISTENCY_CHECK', true),
    ],
];
