<?php

declare(strict_types=1);

return [
    // Navigation
    'navigation' => [
        'group' => 'Documents',
    ],

    // Features
    'features' => [
        'auto_generate_pdf' => true,
    ],

    // Resources
    'resources' => [
        'navigation_sort' => [
            'docs' => 10,
            'doc_templates' => 20,
            'sequences' => 90,
            'email_templates' => 91,
            'pending_approvals' => 15,
            'aging_report' => 100,
        ],
    ],
];
