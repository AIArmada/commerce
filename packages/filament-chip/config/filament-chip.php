<?php

declare(strict_types=1);

return [
    'navigation_group' => 'CHIP Operations',

    'navigation_badge_color' => 'primary',

    'polling_interval' => '45s',

    'resources' => [
        'navigation_sort' => [
            'purchases' => 10,
            'payments' => 20,
            'clients' => 30,
            'bank_accounts' => 40,
            'webhooks' => 50,
            'send_instructions' => 60,
            'send_limits' => 70,
            'send_webhooks' => 80,
            'company_statements' => 90,
        ],
    ],

    'tables' => [
        'created_on_format' => 'Y-m-d H:i:s',
        'amount_precision' => 2,
    ],
];
