<?php

declare(strict_types=1);

return [
    'title' => 'Invois',
    'singular' => 'Invois',
    'plural' => 'Invois',

    'table' => [
        'number' => 'Nombor',
        'customer' => 'Pelanggan',
        'gateway' => 'Gateway',
        'status' => 'Status',
        'amount' => 'Jumlah',
        'date' => 'Tarikh',
        'due_date' => 'Tarikh Akhir',
        'paid_at' => 'Dibayar',
    ],

    'status' => [
        'paid' => 'Dibayar',
        'open' => 'Terbuka',
        'draft' => 'Draf',
        'void' => 'Batal',
        'uncollectible' => 'Tidak Boleh Dikutip',
    ],

    'actions' => [
        'download' => 'Muat Turun PDF',
        'view' => 'Lihat',
        'view_external' => 'Lihat di :gateway Dashboard',
    ],

    'empty' => [
        'title' => 'Tiada invois',
        'description' => 'Invois akan muncul di sini apabila langganan diproses.',
    ],
];
