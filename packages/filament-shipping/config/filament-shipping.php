<?php

declare(strict_types=1);

return [

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
