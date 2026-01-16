<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    'navigation' => [
        'group' => 'Catalog',
        'sort' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */

    'tables' => [
        'poll' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */

    'features' => [
        'import_export' => true,
        'bulk_edit' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */

    'resources' => [
        'product' => [
            'class' => \AIArmada\FilamentProducts\Resources\ProductResource::class,
        ],
        'category' => [
            'class' => \AIArmada\FilamentProducts\Resources\CategoryResource::class,
        ],
        'collection' => [
            'class' => \AIArmada\FilamentProducts\Resources\CollectionResource::class,
        ],
        'attribute' => [
            'class' => \AIArmada\FilamentProducts\Resources\AttributeResource::class,
        ],
        'attribute_group' => [
            'class' => \AIArmada\FilamentProducts\Resources\AttributeGroupResource::class,
        ],
        'attribute_set' => [
            'class' => \AIArmada\FilamentProducts\Resources\AttributeSetResource::class,
        ],
    ],

];
