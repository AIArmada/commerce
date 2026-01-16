<?php

declare(strict_types=1);

return [
    /* Navigation */
    'navigation' => [
        'group' => 'Sales',
        'sort' => 1,
    ],

    /* Tables */
    'tables' => [
        'poll_interval' => '30s',
        'date_format' => 'd M Y, H:i',
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
