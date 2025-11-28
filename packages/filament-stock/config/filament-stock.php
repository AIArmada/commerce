<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation Group
    |--------------------------------------------------------------------------
    |
    | Controls the navigation group label used by all Filament stock
    | resources and widgets. Set to `null` to leave stock at the root level.
    |
    */
    'navigation_group' => 'E-commerce',

    /*
    |--------------------------------------------------------------------------
    | Polling Interval
    |--------------------------------------------------------------------------
    |
    | Configure how frequently (in seconds) stock tables should poll for
    | updates. Accepts either an integer (seconds) or a string supported by
    | Filament (e.g. "30s"). Use `null` to disable polling entirely.
    |
    */
    'polling_interval' => 60,

    /*
    |--------------------------------------------------------------------------
    | Resource Configuration
    |--------------------------------------------------------------------------
    |
    | Fine tune resource-specific behaviours such as navigation ordering.
    |
    */
    'resources' => [
        'navigation_sort' => [
            'stock_transactions' => 50,
            'stock_reservations' => 51,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stockable Models
    |--------------------------------------------------------------------------
    |
    | Define the models that can have stock tracked. Each entry should include
    | the Eloquent model class, a human readable label, and the attribute
    | used for display. Example:
    |
    | 'stockables' => [
    |     [
    |         'label' => 'Products',
    |         'model' => App\Models\Product::class,
    |         'title_attribute' => 'name',
    |         'search_attributes' => ['name', 'sku'],
    |     ],
    | ],
    |
    */
    'stockables' => [],

    /*
    |--------------------------------------------------------------------------
    | Low Stock Threshold
    |--------------------------------------------------------------------------
    |
    | The default threshold for determining when stock is considered low.
    | This is used in the stats widget and resource displays.
    |
    */
    'low_stock_threshold' => env('FILAMENT_STOCK_LOW_THRESHOLD', 10),

    /*
    |--------------------------------------------------------------------------
    | Default Reservation Extension Minutes
    |--------------------------------------------------------------------------
    |
    | Default number of minutes to extend a reservation when using the
    | ExtendReservationAction. Users can modify this value in the action modal.
    |
    */
    'default_extension_minutes' => env('FILAMENT_STOCK_EXTENSION_MINUTES', 30),
];
