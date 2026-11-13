<?php

declare(strict_types=1);

return [
    'type' => [
        'simple' => 'Produk Mudah',
        'configurable' => 'Produk Boleh Konfigurasi',
        'composite' => 'Pakej Komposit',
        'digital' => 'Produk Digital',
        'subscription' => 'Langganan',
    ],
    'composite_price_mode' => [
        'fixed' => 'Harga pakej tetap',
        'sum' => 'Jumlah harga komponen',
    ],
    'status' => [
        'draft' => 'Draf',
        'active' => 'Aktif',
        'disabled' => 'Dilumpuhkan',
        'archived' => 'Diarkib',
    ],
    'visibility' => [
        'catalog' => 'Katalog sahaja',
        'search' => 'Carian sahaja',
        'catalog_search' => 'Katalog & Carian',
        'individual' => 'Individu (tidak disenarai)',
        'hidden' => 'Tersembunyi',
    ],
];
