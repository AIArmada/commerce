<?php

declare(strict_types=1);

return [

    /* Navigation */
    'navigation' => [
        'group' => env('FILAMENT_COMMUNICATIONS_NAV_GROUP', 'Communications'),
        'sort' => (int) env('FILAMENT_COMMUNICATIONS_NAV_SORT', 80),
    ],

    /* Resources */
    'resources' => [
        'communications' => [
            'enabled' => (bool) env('FILAMENT_COMMUNICATIONS_RESOURCE_COMMUNICATIONS', true),
        ],
        'deliveries' => [
            'enabled' => (bool) env('FILAMENT_COMMUNICATIONS_RESOURCE_DELIVERIES', true),
        ],
        'threads' => [
            'enabled' => (bool) env('FILAMENT_COMMUNICATIONS_RESOURCE_THREADS', true),
        ],
        'templates' => [
            'enabled' => (bool) env('FILAMENT_COMMUNICATIONS_RESOURCE_TEMPLATES', true),
        ],
        'preferences' => [
            'enabled' => (bool) env('FILAMENT_COMMUNICATIONS_RESOURCE_PREFERENCES', true),
        ],
        'suppressions' => [
            'enabled' => (bool) env('FILAMENT_COMMUNICATIONS_RESOURCE_SUPPRESSIONS', true),
        ],
        'batches' => [
            'enabled' => (bool) env('FILAMENT_COMMUNICATIONS_RESOURCE_BATCHES', true),
        ],
    ],

];
