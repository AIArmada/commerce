<?php

declare(strict_types=1);

return [
    'navigation_group' => 'J&T Express',

    'navigation_badge_color' => 'primary',

    'polling_interval' => '30s',

    'resources' => [
        'navigation_sort' => [
            'orders' => 10,
            'tracking_events' => 20,
            'webhook_logs' => 30,
        ],
    ],

    'tables' => [
        'datetime_format' => 'Y-m-d H:i:s',
    ],
];
