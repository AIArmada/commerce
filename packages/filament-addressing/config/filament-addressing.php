<?php

declare(strict_types=1);

return [
    'navigation' => [
        'enabled' => true,
        'group' => 'Addressing',
        'sort' => 80,
        'icons' => [
            'countries' => 'heroicon-o-globe-alt',
            'areas' => 'heroicon-o-map',
            'addresses' => 'heroicon-o-map-pin',
            'snapshots' => 'heroicon-o-document-text',
        ],
    ],

    'tables' => [
        'default_pagination' => 25,
        'search_debounce' => '500ms',
    ],

    'features' => [
        'country_editing' => false,
        'area_import' => true,
        'area_export' => true,
        'address_export' => false,
        'show_provider_payload' => false,
        'show_source_payload' => false,
    ],

    'resources' => [
        'countries' => [
            'enabled' => true,
            'read_only' => true,
            'model' => \AIArmada\Addressing\Models\AddressCountry::class,
        ],

        'areas' => [
            'enabled' => true,
            'read_only' => false,
            'model' => \AIArmada\Addressing\Models\AddressArea::class,
        ],

        'addresses' => [
            'enabled' => false,
            'read_only' => false,
            'model' => \AIArmada\Addressing\Models\Address::class,
        ],

        'snapshots' => [
            'enabled' => false,
            'read_only' => true,
            'model' => \AIArmada\Addressing\Models\AddressSnapshot::class,
        ],
    ],
];
