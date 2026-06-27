<?php

declare(strict_types=1);

return [
    'navigation' => [
        'group' => 'CRM',
    ],
    'features' => [
        'merge_customers' => true,
        'segment_rebuild' => false,
        'address_validation' => false,
    ],
    'resources' => [
        'navigation_sort' => [
            'customers' => 1,
            'segments' => 2,
        ],
    ],
    'pages' => [
        'navigation_sort' => [
            'merge_customers' => 10,
            'segment_rebuild' => 99,
            'address_validation' => 100,
        ],
    ],
];
