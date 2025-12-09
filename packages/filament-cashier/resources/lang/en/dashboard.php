<?php

declare(strict_types=1);

return [
    'title' => 'Dashboard',

    'widgets' => [
        'total_mrr' => [
            'label' => 'Total MRR',
            'description' => 'Monthly Recurring Revenue',
        ],
        'total_subscribers' => [
            'label' => 'Active Subscribers',
            'description' => 'Total active subscriptions',
        ],
        'gateway_breakdown' => [
            'label' => 'Revenue by Gateway',
            'stripe' => 'Stripe',
            'chip' => 'CHIP',
        ],
        'churn' => [
            'label' => 'Monthly Churn',
            'description' => 'Cancellations this month',
        ],
        'comparison' => [
            'label' => 'Gateway Comparison',
            'revenue' => 'Revenue',
            'subscribers' => 'Subscribers',
        ],
    ],
];
