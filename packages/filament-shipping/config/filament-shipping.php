<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    'navigation_group' => 'Shipping',

    'navigation_sort' => 50,

    /*
    |--------------------------------------------------------------------------
    | Table Settings
    |--------------------------------------------------------------------------
    */

    'table_poll_interval' => '30s',

    /*
    |--------------------------------------------------------------------------
    | Shipping Methods
    |--------------------------------------------------------------------------
    */

    'shipping_methods' => [
        'standard' => 'Standard',
        'express' => 'Express',
        'overnight' => 'Overnight',
        'pickup' => 'Self Pickup',
    ],

    /*
    |--------------------------------------------------------------------------
    | Carriers
    |--------------------------------------------------------------------------
    |
    | Available shipping carriers. If empty, will fall back to shipping.drivers
    | config or default list.
    |
    */

    'carriers' => [
        // Will use shipping.drivers if empty
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */

    'features' => [
        'enable_fulfillment_queue' => true,
        'enable_manifest_page' => true,
        'enable_dashboard' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fulfillment Queue
    |--------------------------------------------------------------------------
    */

    'fulfillment' => [
        'urgent_threshold_hours' => 48,
        'old_threshold_hours' => 24,
    ],

];
