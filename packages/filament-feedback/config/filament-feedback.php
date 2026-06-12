<?php

declare(strict_types=1);

return [
    'navigation' => [
        'group' => 'Feedback',
        'icon' => 'heroicon-o-chat-bubble-left-right',
        'sort' => 70,
    ],

    'tables' => [
        'default_pagination' => 25,
    ],

    'features' => [
        'forms' => true,
        'responses' => true,
        'invitations' => true,
        'templates' => true,
        'testimonials' => true,
        'analytics_dashboard' => true,
        'exports' => true,
    ],

    'resources' => [
        'enabled' => [
            'feedback_form' => true,
            'feedback_response' => true,
            'feedback_invitation' => true,
            'feedback_template' => true,
            'feedback_testimonial' => true,
        ],
    ],
];
