<?php

declare(strict_types=1);

return [
    'title' => 'Papan Pemuka',

    'widgets' => [
        'total_mrr' => [
            'label' => 'Jumlah MRR',
            'description' => 'Pendapatan Berulang Bulanan',
        ],
        'total_subscribers' => [
            'label' => 'Pelanggan Aktif',
            'description' => 'Jumlah langganan aktif',
        ],
        'gateway_breakdown' => [
            'label' => 'Pendapatan Mengikut Gateway',
            'stripe' => 'Stripe',
            'chip' => 'CHIP',
        ],
        'churn' => [
            'label' => 'Churn Bulanan',
            'description' => 'Pembatalan bulan ini',
        ],
        'comparison' => [
            'label' => 'Perbandingan Gateway',
            'revenue' => 'Pendapatan',
            'subscribers' => 'Pelanggan',
        ],
    ],
];
