<?php

declare(strict_types=1);

return [
    'database' => [
        'table_prefix' => '',
        'json_column_type' => 'jsonb',

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
        'include_global_templates' => false,
    ],

    'defaults' => [
        'form_status' => 'draft',
        'visibility' => 'private',
        'response_status' => 'draft',
        'invitation_expiry_days' => 14,
    ],

    'features' => [
        'anonymous_responses' => true,
        'invitations' => true,
        'testimonials' => true,
        'templates' => true,
        'analytics' => true,
    ],

    'integrations' => [
        'events' => true,
        'certificates' => true,
        'engagement' => true,
    ],

    'http' => [
        'routes_enabled' => false,
        'route_prefix' => 'feedback',
        'middleware' => ['web'],
    ],

    'cache' => [
        'analytics_ttl_seconds' => 300,
    ],

    'logging' => [
        'enabled' => false,
    ],
];
