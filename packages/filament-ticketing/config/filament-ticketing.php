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
    'ticketable_types' => [
        // App-specific ticketable classes, e.g.:
        // \AIArmada\Events\Models\Event::class,
    ],
    'allowed_ticketable_types' => [
        // Restrict to specific ticketable types (whitelist). Empty = all registered allowed.
    ],
];
