<?php

declare(strict_types=1);

return [
    'navigation' => [
        'group' => 'Ticketing',
    ],
    'resources' => [
        'enabled' => [
            'ticket_type' => true,
            'pass' => true,
            'pass_holder' => true,
            'pass_transfer' => true,
        ],
        'navigation_sort' => [
            'ticket_type' => 1,
            'pass' => 2,
            'pass_holder' => 3,
            'pass_transfer' => 4,
        ],
    ],
];
