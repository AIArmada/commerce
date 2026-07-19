<?php

declare(strict_types=1);

return [
    'navigation' => [
        'group' => 'Events',
    ],
    'resources' => [
        'enabled' => [
            'event' => true,
            'occurrence' => true,
            'session' => true,
            'venue' => true,
            'venue_space' => true,
            'registration' => true,
            'registration_participant' => true,
            'ticket_type' => true,
            'attendance' => true,
            'change_log' => true,
            'event_template' => true,
        ],
        'navigation_sort' => [
            'event' => 1,
            'occurrence' => 2,
            'session' => 3,
            'venue' => 4,
            'venue_space' => 5,
            'registration' => 10,
            'ticket_type' => 11,
            'registration_participant' => 11,
            'attendance' => 12,
            'change_log' => 99,
            'event_template' => 98,
        ],
    ],
];
