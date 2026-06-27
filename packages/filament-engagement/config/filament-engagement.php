<?php

declare(strict_types=1);

return [
    'navigation' => [
        'group' => 'Engagement',
    ],
    'resources' => [
        'enabled' => [
            'follow' => true,
            'bookmark' => true,
            'collection' => true,
            'response' => true,
            'reaction' => true,
            'subscription' => true,
            'reminder' => true,
        ],
        'navigation_sort' => [
            'follow' => 1,
            'bookmark' => 2,
            'collection' => 3,
            'response' => 4,
            'reaction' => 5,
            'subscription' => 6,
            'reminder' => 7,
        ],
    ],
];
