<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation Group
    |--------------------------------------------------------------------------
    |
    | The navigation group where the cart resource will be displayed.
    |
    */
    'navigation_group' => 'E-commerce',

    /*
    |--------------------------------------------------------------------------
    | Polling Interval
    |--------------------------------------------------------------------------
    |
    | The polling interval in seconds for real-time updates in the cart table.
    |
    */
    'polling_interval' => 30,

    /*
    |--------------------------------------------------------------------------
    | Global Conditions
    |--------------------------------------------------------------------------
    |
    | Enable automatic application of global conditions to all carts.
    | When enabled, conditions marked as "global" will be automatically
    | applied to new carts and when items are added.
    |
    */
    'enable_global_conditions' => true,

    /*
    |--------------------------------------------------------------------------
    | Dynamic Rule Factory
    |--------------------------------------------------------------------------
    |
    | Controls which rules factory is used when Filament resolves cart
    | instances. The default leverages the built-in cart package factory to
    | hydrate persisted dynamic condition rules.
    |
    */
    'dynamic_rules_factory' => AIArmada\Cart\Services\BuiltInRulesFactory::class,

    /*
    |--------------------------------------------------------------------------
    | Event Synchronization
    |--------------------------------------------------------------------------
    |
    | Configure how cart events are synchronized to normalized models.
    |
    */
    'synchronization' => [
        /*
        | Queue synchronization for better performance
        | Note: Requires queue configuration in your Laravel app
        */
        'queue_sync' => false,

        /*
        | Queue connection to use for synchronization jobs
        */
        'queue_connection' => 'default',

        /*
        | Queue name for synchronization jobs
        */
        'queue_name' => 'cart-sync',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Configuration
    |--------------------------------------------------------------------------
    |
    | Configure navigation sort order for Filament resources.
    |
    */
    'resources' => [
        /*
        | Navigation sort order for resources
        */
        'navigation_sort' => [
            'carts' => 30,
        ],
    ],
];
