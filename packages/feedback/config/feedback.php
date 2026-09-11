<?php

declare(strict_types=1);

return [
    'database' => [
        'table_prefix' => '',
        'json_column_type' => env('FEEDBACK_JSON_COLUMN_TYPE', 'jsonb'),
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
            'form_analytics' => 'feedback_form_analytics',
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

    'security' => [
        'invitation_rate_limit' => [
            'max_attempts' => 60,
            'decay_seconds' => 60,
        ],
    ],

    'analytics' => [
        'dashboard_cache_ttl' => 30,
    ],

    'http' => [
        'route_prefix' => 'feedback',
    ],
];
