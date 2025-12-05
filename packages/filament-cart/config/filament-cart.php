<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'tables' => [
            'snapshots' => 'cart_snapshots',
            'snapshot_items' => 'cart_snapshot_items',
            'snapshot_conditions' => 'cart_snapshot_conditions',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation_group' => 'E-Commerce',

    'resources' => [
        'navigation_sort' => [
            'carts' => 30,
            'cart_items' => 31,
            'cart_conditions' => 32,
            'conditions' => 33,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */
    'polling_interval' => '30s',

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'enable_global_conditions' => true,
    'dynamic_rules_factory' => AIArmada\Cart\Services\BuiltInRulesFactory::class,

    /*
    |--------------------------------------------------------------------------
    | Synchronization
    |--------------------------------------------------------------------------
    */
    'synchronization' => [
        'queue_sync' => false,
        'queue_connection' => 'default',
        'queue_name' => 'cart-sync',
    ],
];
