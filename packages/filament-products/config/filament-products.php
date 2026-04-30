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
        'resources' => [
            'products' => 1,
            'categories' => 2,
            'collections' => 3,
            'attributes' => 40,
            'attribute_groups' => 41,
            'attribute_sets' => 42,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'collections' => true,
        'attributes' => true,
        'bulk_edit' => true,
        'import_export' => true,
    ],
];
