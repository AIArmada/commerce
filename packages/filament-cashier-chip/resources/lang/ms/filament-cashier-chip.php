<?php

declare(strict_types=1);

return [
    'navigation' => [
        'group' => 'Pengebilan',
        'subscriptions' => 'Langganan',
        'customers' => 'Pelanggan',
        'invoices' => 'Invois',
        'dashboard' => 'Papan Pemuka Pengebilan',
    ],

    'subscription' => [
        'label' => 'Langganan',
        'plural' => 'Langganan',
        'status' => [
            'active' => 'Aktif',
            'trialing' => 'Percubaan',
            'canceled' => 'Dibatalkan',
            'past_due' => 'Tertunggak',
            'paused' => 'Dijeda',
            'incomplete' => 'Tidak Lengkap',
            'incomplete_expired' => 'Tidak Lengkap Tamat',
            'unpaid' => 'Belum Dibayar',
        ],
        'actions' => [
            'cancel' => 'Batalkan Langganan',
            'cancel_now' => 'Batalkan Segera',
            'resume' => 'Sambung Langganan',
            'pause' => 'Jeda Langganan',
            'unpause' => 'Nyahjeda Langganan',
            'extend_trial' => 'Lanjutkan Percubaan',
            'update_quantity' => 'Kemas Kini Kuantiti',
            'swap_plan' => 'Tukar Pelan',
            'sync_status' => 'Segerakkan Status',
        ],
        'messages' => [
            'canceled' => 'Langganan telah dibatalkan.',
            'resumed' => 'Langganan kini aktif semula.',
            'paused' => 'Langganan telah dijeda.',
            'unpaused' => 'Langganan kini aktif.',
            'trial_extended' => 'Percubaan telah dilanjutkan.',
            'quantity_updated' => 'Kuantiti langganan telah dikemas kini.',
            'status_synced' => 'Status langganan telah dikira semula.',
        ],
    ],

    'customer' => [
        'label' => 'Pelanggan',
        'plural' => 'Pelanggan',
        'actions' => [
            'create_in_chip' => 'Cipta dalam Chip',
            'sync_to_chip' => 'Segerakkan ke Chip',
            'refresh_payment' => 'Muat Semula Kaedah Pembayaran',
            'add_payment' => 'Tambah Kaedah Pembayaran',
            'view_in_chip' => 'Lihat dalam Chip',
        ],
        'messages' => [
            'created_in_chip' => 'Pelanggan dicipta dalam Chip.',
            'synced_to_chip' => 'Pelanggan disegerakkan ke Chip.',
            'payment_refreshed' => 'Kaedah pembayaran dimuat semula.',
        ],
    ],

    'invoice' => [
        'label' => 'Invois',
        'plural' => 'Invois',
        'status' => [
            'created' => 'Dicipta',
            'pending' => 'Menunggu',
            'paid' => 'Dibayar',
            'captured' => 'Ditangkap',
            'completed' => 'Selesai',
            'failed' => 'Gagal',
            'cancelled' => 'Dibatalkan',
            'refund_pending' => 'Bayaran Balik Menunggu',
            'refunded' => 'Dibayar Balik',
            'partially_refunded' => 'Dibayar Balik Sebahagian',
        ],
        'actions' => [
            'download_pdf' => 'Muat Turun PDF',
            'send_invoice' => 'Hantar Invois',
            'mark_as_paid' => 'Tandakan Sebagai Dibayar',
            'view_checkout' => 'Lihat Checkout',
            'copy_url' => 'Salin URL Checkout',
        ],
        'messages' => [
            'pdf_generated' => 'PDF telah dijana.',
            'invoice_sent' => 'Invois telah dihantar.',
            'marked_as_paid' => 'Invois ditandakan sebagai dibayar.',
        ],
    ],

    'widgets' => [
        'mrr' => [
            'title' => 'Hasil Berulang Bulanan',
            'no_previous_data' => 'Tiada data sebelumnya',
            'from_last_month' => 'dari bulan lepas',
        ],
        'active_subscribers' => [
            'title' => 'Pelanggan Aktif',
            'from_last_month' => 'dari bulan lepas',
        ],
        'churn_rate' => [
            'title' => 'Kadar Churn',
            'same_as_last' => 'Sama seperti bulan lepas',
        ],
        'trial_conversions' => [
            'title' => 'Kadar Penukaran Percubaan',
            'active_trials' => 'Percubaan Aktif',
            'currently_trialing' => 'Sedang dalam percubaan',
        ],
        'revenue_chart' => [
            'title' => 'Trend Hasil (12 Bulan Terakhir)',
        ],
        'subscription_distribution' => [
            'title' => 'Pengagihan Langganan',
        ],
    ],

    'dashboard' => [
        'title' => 'Papan Pemuka Pengebilan',
        'subtitle' => 'Pantau hasil berulang anda, pertumbuhan pelanggan, dan metrik pengebilan.',
    ],
];
