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
    ],

    /* Payment Gateways */
    'payment_gateways' => [
        'stripe' => 'Stripe',
        'chip' => 'CHIP',
        'manual' => 'Manual',
    ],
];
