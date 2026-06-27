<?php

declare(strict_types=1);

return [
    /* Navigation */
    'navigation' => [
        'group' => 'Sales',
        'sort' => 1,
    ],

    /* Pages */
    'pages' => [
        'timeline' => true,
        'fulfillment' => true,
        'navigation_sort' => [
            'fulfillment' => 5,
            'timeline' => 6,
        ],
    ],

    /* Payment Gateways */
    'payment_gateways' => [
        'stripe' => 'Stripe',
        'chip' => 'CHIP',
        'manual' => 'Manual',
    ],

    /* Features */
    'features' => [
        'enable_invoice_download' => true,
    ],
];
