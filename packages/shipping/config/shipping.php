<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Shipping Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default shipping driver that will be used when
    | no specific driver is requested. The driver must be registered in the
    | drivers array below or extended via ShippingManager::extend().
    |
    */
    'default' => env('SHIPPING_DRIVER', 'manual'),

    /*
    |--------------------------------------------------------------------------
    | Shipping Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the shipping drivers for your application.
    | Carrier packages auto-register via extend(). This configuration is
    | for built-in drivers or manual overrides.
    |
    */
    'drivers' => [
        'manual' => [
            'driver' => 'manual',
            'name' => 'Manual Shipping',
            'default_rate' => 1000, // RM10.00 in cents
            'estimated_days' => 3,
            'free_shipping_threshold' => null,
        ],

        'flat_rate' => [
            'driver' => 'flat_rate',
            'name' => 'Flat Rate Shipping',
            'rates' => [
                'standard' => [
                    'name' => 'Standard Delivery',
                    'rate' => 800, // RM8.00
                    'estimated_days' => 3,
                ],
                'express' => [
                    'name' => 'Express Delivery',
                    'rate' => 1500, // RM15.00
                    'estimated_days' => 1,
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Shopping Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how rate shopping works when comparing rates from multiple
    | carriers. The strategy determines which rate is selected by default.
    |
    */
    'rate_shopping' => [
        'enabled' => true,
        'strategy' => 'cheapest', // cheapest, fastest, preferred
        'preferred_carrier' => null,
        'cache_ttl' => 300, // 5 minutes
        'fallback_to_manual' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Carrier Priority
    |--------------------------------------------------------------------------
    |
    | When using the 'preferred' rate selection strategy, carriers will be
    | tried in this priority order. Lower numbers have higher priority.
    |
    */
    'carrier_priority' => [
        // 'jnt' => 1,
        // 'poslaju' => 2,
        // 'gdex' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Free Shipping Configuration
    |--------------------------------------------------------------------------
    |
    | Configure global free shipping rules. These can be overridden by
    | zone-specific or carrier-specific settings.
    |
    */
    'free_shipping' => [
        'enabled' => false,
        'threshold' => 15000, // RM150.00 in cents
        'applies_to' => ['standard'], // Which services get free shipping
        'zones' => [], // Empty = all zones, or specify zone codes
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic tracking synchronization and webhook handling.
    |
    */
    'tracking' => [
        'auto_sync' => true,
        'sync_interval' => 3600, // 1 hour in seconds
        'max_tracking_age' => 30, // days to keep syncing
        'webhook_enabled' => true,
        'webhook_secret' => env('SHIPPING_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Label Generation
    |--------------------------------------------------------------------------
    |
    | Configure shipping label generation and storage.
    |
    */
    'labels' => [
        'format' => 'pdf', // pdf, png, zpl
        'size' => 'a6', // a4, a6, 4x6
        'storage_disk' => 'local',
        'storage_path' => 'shipping-labels',
        'keep_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Returns Configuration
    |--------------------------------------------------------------------------
    |
    | Configure return merchandise authorization (RMA) settings.
    |
    */
    'returns' => [
        'enabled' => true,
        'auto_approve' => false,
        'return_window_days' => 14,
        'generate_return_label' => true,
        'restocking_fee_percent' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default currency for shipping rates and costs.
    |
    */
    'currency' => 'MYR',

    /*
    |--------------------------------------------------------------------------
    | Weight Unit
    |--------------------------------------------------------------------------
    |
    | The weight unit used throughout the shipping package.
    | Supported: 'g' (grams), 'kg' (kilograms), 'oz' (ounces), 'lb' (pounds)
    |
    */
    'weight_unit' => 'g',

    /*
    |--------------------------------------------------------------------------
    | Dimension Unit
    |--------------------------------------------------------------------------
    |
    | The dimension unit used for package measurements.
    | Supported: 'cm' (centimeters), 'in' (inches)
    |
    */
    'dimension_unit' => 'cm',
];
