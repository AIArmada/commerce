<?php

declare(strict_types=1);

return [
    'navigation' => [
        'group' => 'Feedback',
    ],

    'resources' => [
        'enabled' => [
            'feedback_form' => true,
            'feedback_response' => true,
            'feedback_invitation' => true,
            'feedback_template' => true,
            'feedback_testimonial' => true,
        ],
        'navigation_sort' => [
            'feedback_form' => 1,
            'feedback_response' => 2,
            'feedback_invitation' => 3,
            'feedback_template' => 4,
            'feedback_testimonial' => 5,
        ],
    ],

    'pages' => [
        'navigation_sort' => [
            'dashboard' => 10,
        ],
    ],
];
