<?php

declare(strict_types=1);

return [
    'title' => 'Pengurusan Gateway',

    'setup' => [
        'title' => 'Penyediaan Gateway Diperlukan',
        'description' => 'Tiada gateway pembayaran dikonfigurasi. Pasang sekurang-kurangnya satu pakej gateway untuk mula menerima pembayaran.',
        'no_gateway_title' => 'Tiada Gateway Tersedia',
        'no_gateway_description' => 'Pasang sekurang-kurangnya satu gateway pembayaran untuk meneruskan.',
        'stripe' => [
            'name' => 'Stripe',
            'description' => 'Terima kad kredit, ACH, dan pembayaran antarabangsa.',
            'install' => 'composer require laravel/cashier',
        ],
        'chip' => [
            'name' => 'CHIP',
            'description' => 'Terima FPX, e-wallet, dan pembayaran Malaysia.',
            'install' => 'composer require aiarmada/cashier-chip',
        ],
    ],

    'management' => [
        'title' => 'Pengurusan Gateway',
        'navigation' => 'Pengurusan Gateway',
        'health_title' => 'Status Kesihatan Gateway',
        'health_description' => 'Pantau kesihatan dan sambungan gateway pembayaran anda.',
        'last_check' => 'Terakhir disemak',
        'default_gateway' => 'Gateway Lalai',
        'config_title' => 'Panduan Konfigurasi',
        'config_description' => 'Pembolehubah persekitaran yang diperlukan untuk setiap gateway.',
        'stripe_title' => 'Konfigurasi Stripe',
        'stripe_key_desc' => 'Kunci publishable Stripe anda',
        'stripe_secret_desc' => 'Kunci rahsia Stripe anda',
        'stripe_webhook_desc' => 'Rahsia penandatanganan webhook Stripe anda',
        'chip_title' => 'Konfigurasi CHIP',
        'chip_brand_desc' => 'ID jenama CHIP anda',
        'chip_api_desc' => 'Kunci API CHIP anda',
        'chip_webhook_desc' => 'Token pengesahan webhook CHIP anda',
        'features_title' => 'Perbandingan Ciri Gateway',
        'feature' => 'Ciri',
    ],

    'status' => [
        'title' => 'Status Gateway',
        'available' => 'Tersedia',
        'unavailable' => 'Tidak Dipasang',
        'default' => 'Lalai',
    ],

    'health' => [
        'title' => 'Kesihatan Gateway',
        'healthy' => 'Sihat',
        'degraded' => 'Merosot',
        'down' => 'Tidak Berfungsi',
        'error' => 'Ralat',
        'unknown' => 'Tidak Diketahui',
        'not_configured' => 'Tidak Dikonfigurasi',
        'sdk_missing' => 'SDK tidak dipasang',
        'last_checked' => 'Terakhir disemak',
    ],

    'fields' => [
        'gateway' => 'Gateway',
    ],

    'actions' => [
        'test_connection' => 'Uji Sambungan',
        'set_default' => 'Tetapkan Gateway Lalai',
        'configure' => 'Konfigurasi',
    ],

    'notifications' => [
        'connection_success' => 'Sambungan :gateway berjaya',
        'connection_failed' => 'Sambungan :gateway gagal',
        'default_set' => ':gateway ditetapkan sebagai gateway lalai',
    ],
];
