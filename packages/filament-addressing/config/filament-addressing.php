<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\AddressSnapshot;

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
            'model' => AddressCountry::class,
        ],

        'areas' => [
            'enabled' => true,
            'read_only' => false,
            'model' => AddressArea::class,
        ],

        'addresses' => [
            'enabled' => false,
            'read_only' => false,
            'model' => Address::class,
        ],

        'snapshots' => [
            'enabled' => false,
            'read_only' => true,
            'model' => AddressSnapshot::class,
        ],
    ],
];
