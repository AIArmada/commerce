<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation_group' => 'Inventory',

    'resources' => [
        'navigation_sort' => [
            'locations' => 10,
            'levels' => 20,
            'movements' => 30,
            'allocations' => 40,
            'batches' => 50,
            'serials' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */
    'polling_interval' => '45s',

    'tables' => [
        'date_format' => 'Y-m-d H:i:s',
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        // Widgets
        'stats_widget' => env('FILAMENT_INVENTORY_STATS_WIDGET', true),
        'low_stock_widget' => env('FILAMENT_INVENTORY_LOW_STOCK_WIDGET', true),
        'expiring_batches_widget' => env('FILAMENT_INVENTORY_EXPIRING_BATCHES_WIDGET', true),
        'reorder_suggestions_widget' => env('FILAMENT_INVENTORY_REORDER_SUGGESTIONS_WIDGET', true),
        'backorders_widget' => env('FILAMENT_INVENTORY_BACKORDERS_WIDGET', true),
        'valuation_widget' => env('FILAMENT_INVENTORY_VALUATION_WIDGET', true),
        'kpi_widget' => env('FILAMENT_INVENTORY_KPI_WIDGET', true),
        'movement_trends_chart' => env('FILAMENT_INVENTORY_MOVEMENT_TRENDS_CHART', true),
        'abc_analysis_chart' => env('FILAMENT_INVENTORY_ABC_ANALYSIS_CHART', true),

        // Resources
        'batch_resource' => env('FILAMENT_INVENTORY_BATCH_RESOURCE', true),
        'serial_resource' => env('FILAMENT_INVENTORY_SERIAL_RESOURCE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'stats_ttl' => env('FILAMENT_INVENTORY_STATS_CACHE_TTL', 60),
    ],
];
