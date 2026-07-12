<?php

declare(strict_types=1);

return [
    'database' => [
        'table_prefix' => '',
        'tables' => [
            'forms' => 'feedback_forms',
            'sections' => 'feedback_sections',
            'questions' => 'feedback_questions',
            'question_options' => 'feedback_question_options',
            'responses' => 'feedback_responses',
            'answers' => 'feedback_answers',
            'invitations' => 'feedback_invitations',
            'templates' => 'feedback_templates',
            'testimonials' => 'feedback_testimonials',
        ],
    ],

    'owner' => [
        'enabled' => true,
        'auto_assign_on_create' => true,
        'include_global' => false,
    ],

    'defaults' => [
        'invitation_expiry_days' => 14,
    ],

    'features' => [
        'testimonials' => true,
    ],

    'http' => [
        'route_prefix' => 'feedback',
    ],
];
