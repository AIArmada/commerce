<?php

declare(strict_types=1);

return [
    'title' => 'Langganan',
    'singular' => 'Langganan',
    'plural' => 'Langganan',

    'table' => [
        'user' => 'Pelanggan',
        'gateway' => 'Gateway',
        'type' => 'Jenis',
        'plan' => 'Pelan',
        'status' => 'Status',
        'amount' => 'Jumlah',
        'quantity' => 'Kuantiti',
        'trial_ends_at' => 'Percubaan Tamat',
        'ends_at' => 'Tamat Pada',
        'next_billing' => 'Bil Seterusnya',
        'created_at' => 'Dicipta',
    ],

    'status' => [
        'active' => 'Aktif',
        'on_trial' => 'Dalam Percubaan',
        'past_due' => 'Tertunggak',
        'canceled' => 'Dibatalkan',
        'grace_period' => 'Tempoh Ihsan',
        'paused' => 'Dijeda',
        'incomplete' => 'Tidak Lengkap',
        'expired' => 'Tamat Tempoh',
    ],

    'cycle' => [
        'monthly' => 'Bulanan',
        'quarterly' => 'Suku Tahun',
        'yearly' => 'Tahunan',
    ],

    'tabs' => [
        'all' => 'Semua',
        'active' => 'Aktif',
        'issues' => 'Perlu Perhatian',
    ],

    'filters' => [
        'gateway' => 'Gateway',
        'status' => 'Status',
        'plan' => 'Pelan',
    ],

    'actions' => [
        'cancel' => 'Batal',
        'cancel_heading' => 'Batalkan Langganan :gateway',
        'cancel_description' => 'Ini akan membatalkan langganan. Pelanggan akan mengekalkan akses sehingga akhir tempoh bil semasa.',
        'cancel_success' => 'Langganan berjaya dibatalkan.',

        'cancel_immediately' => 'Batal Segera',
        'cancel_immediately_heading' => 'Batalkan Langganan Segera',
        'cancel_immediately_description' => 'Ini akan membatalkan langganan dengan segera. Pelanggan akan kehilangan akses serta-merta.',
        'cancel_immediately_success' => 'Langganan dibatalkan segera.',

        'resume' => 'Sambung',
        'resume_success' => 'Langganan berjaya disambung.',

        'swap' => 'Tukar Pelan',
        'swap_heading' => 'Tukar Pelan Langganan',
        'swap_plan_label' => 'Pelan Baru',
        'swap_prorate_label' => 'Prorata caj',
        'swap_success' => 'Pelan berjaya ditukar.',

        'view_external' => 'Lihat di :gateway Dashboard',
    ],

    'create' => [
        'title' => 'Cipta Langganan',
        'steps' => [
            'customer' => 'Pelanggan',
            'gateway' => 'Gateway',
            'plan' => 'Pelan',
            'payment' => 'Pembayaran',
        ],
        'customer_label' => 'Pelanggan',
        'gateway_label' => 'Gateway Pembayaran',
        'gateway_stripe_description' => 'Kad kredit, ACH, pembayaran antarabangsa',
        'gateway_chip_description' => 'FPX, e-wallet, pembayaran Malaysia',
        'plan_label' => 'Pilih Pelan',
        'quantity_label' => 'Kuantiti (Tempat Duduk)',
        'has_trial_label' => 'Sertakan Tempoh Percubaan',
        'trial_days_label' => 'Hari Percubaan',
        'payment_method_label' => 'Kaedah Pembayaran',
        'payment_method_placeholder' => 'Gunakan lalai atau tambah baru',
        'success' => 'Langganan dicipta pada :gateway.',
    ],

    'details' => [
        'title' => 'Butiran Langganan',
        'overview' => 'Gambaran Keseluruhan',
        'gateway_details' => 'Butiran Gateway (:gateway)',
        'billing_info' => 'Maklumat Bil',

        'subscription_id' => 'ID Langganan',
        'customer_id' => 'ID Pelanggan',
        'price_id' => 'ID Harga',
        'current_period' => 'Tempoh Semasa',
        'collection_method' => 'Kaedah Kutipan',
        'default_payment' => 'Kaedah Pembayaran Lalai',
        'schedule_id' => 'ID Jadual',
        'payment_token' => 'Token Pembayaran',
        'next_charge' => 'Tarikh Caj Seterusnya',
    ],

    'bulk' => [
        'cancel' => 'Batalkan Terpilih',
        'cancel_confirm' => 'Ini akan membatalkan: :summary',
        'export' => 'Eksport ke CSV',
    ],

    'empty' => [
        'title' => 'Tiada langganan lagi',
        'description' => 'Cipta langganan untuk bermula.',
    ],
];
