<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\NullOwnerResolver;

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'table_names' => [
        'locations' => env('INVENTORY_LOCATIONS_TABLE', 'inventory_locations'),
        'levels' => env('INVENTORY_LEVELS_TABLE', 'inventory_levels'),
        'movements' => env('INVENTORY_MOVEMENTS_TABLE', 'inventory_movements'),
        'allocations' => env('INVENTORY_ALLOCATIONS_TABLE', 'inventory_allocations'),
        'batches' => env('INVENTORY_BATCHES_TABLE', 'inventory_batches'),
        'serials' => env('INVENTORY_SERIALS_TABLE', 'inventory_serials'),
        'serial_history' => env('INVENTORY_SERIAL_HISTORY_TABLE', 'inventory_serial_history'),
        'cost_layers' => env('INVENTORY_COST_LAYERS_TABLE', 'inventory_cost_layers'),
        'standard_costs' => env('INVENTORY_STANDARD_COSTS_TABLE', 'inventory_standard_costs'),
        'valuation_snapshots' => env('INVENTORY_VALUATION_SNAPSHOTS_TABLE', 'inventory_valuation_snapshots'),
        'backorders' => env('INVENTORY_BACKORDERS_TABLE', 'inventory_backorders'),
        'demand_history' => env('INVENTORY_DEMAND_HISTORY_TABLE', 'inventory_demand_history'),
        'supplier_leadtimes' => env('INVENTORY_SUPPLIER_LEADTIMES_TABLE', 'inventory_supplier_leadtimes'),
        'reorder_suggestions' => env('INVENTORY_REORDER_SUGGESTIONS_TABLE', 'inventory_reorder_suggestions'),
    ],

    'json_column_type' => env('INVENTORY_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'default_reorder_point' => env('INVENTORY_DEFAULT_REORDER_POINT', 10),
    'allocation_strategy' => env('INVENTORY_ALLOCATION_STRATEGY', 'priority'), // priority, fifo, least_stock, single_location
    'allocation_ttl_minutes' => env('INVENTORY_ALLOCATION_TTL', 30),
    'allow_split_allocation' => env('INVENTORY_ALLOW_SPLIT', true),

    /*
    |--------------------------------------------------------------------------
    | Ownership (Multi-Tenancy)
    |--------------------------------------------------------------------------
    |
    | Register a resolver that returns the current owner (merchant, tenant, etc).
    | When enabled, inventory data is automatically scoped to the owner.
    |
    */
    'owner' => [
        'enabled' => env('INVENTORY_OWNER_ENABLED', false),
        'resolver' => NullOwnerResolver::class,
        'include_global' => env('INVENTORY_OWNER_INCLUDE_GLOBAL', true),
        'auto_assign_on_create' => env('INVENTORY_OWNER_AUTO_ASSIGN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart Integration
    |--------------------------------------------------------------------------
    */
    'cart' => [
        'enabled' => env('INVENTORY_CART_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Integration
    |--------------------------------------------------------------------------
    */
    'payment' => [
        'auto_commit' => env('INVENTORY_AUTO_COMMIT', true),
        'events' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    */
    'events' => [
        'low_inventory' => env('INVENTORY_EVENT_LOW', true),
        'out_of_inventory' => env('INVENTORY_EVENT_OUT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup
    |--------------------------------------------------------------------------
    */
    'cleanup' => [
        'keep_expired_for_minutes' => env('INVENTORY_KEEP_EXPIRED', 0),
    ],
];
