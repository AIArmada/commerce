<?php

declare(strict_types=1);

return [
    /* Navigation */

    'navigation_group' => 'E-commerce',

    /* Widgets */

    'widgets' => [
        'currency' => env('AFFILIATES_DEFAULT_CURRENCY', 'MYR'),
    ],

    /* Features */

    'features' => [
        'admin' => [
            'conversions' => true,
            'payouts' => true,
            'programs' => true,
            'commission_management' => true,
            'links' => true,
            'attribution' => true,
            'ranks' => true,
            'support_compliance' => true,
            'fraud_monitoring' => true,
            'reports' => true,
            'network_visualization' => true,
        ],
    ],

    /* Portal */

    'portal' => [
        'panel_id' => env('AFFILIATES_PORTAL_PANEL_ID', 'affiliate'),
        'path' => env('AFFILIATES_PORTAL_PATH', 'affiliate'),
        'domain' => env('AFFILIATES_PORTAL_DOMAIN'),
        'brand_name' => env('AFFILIATES_PORTAL_BRAND_NAME', 'Affiliate Portal'),
        'primary_color' => env('AFFILIATES_PORTAL_PRIMARY_COLOR', '#6366f1'),
        'login_enabled' => env('AFFILIATES_PORTAL_LOGIN_ENABLED', true),
        'registration_enabled' => env('AFFILIATES_PORTAL_REGISTRATION_ENABLED', true),
        'auth_guard' => env('AFFILIATES_PORTAL_AUTH_GUARD', 'web'),
        'features' => [
            'dashboard' => true,
            'profile' => true,
            'links' => true,
            'programs' => true,
            'conversions' => true,
            'payouts' => true,
            'support_compliance' => true,
        ],
    ],

    /* Integrations */

    'integrations' => [
        'filament_cart' => true,
        'filament_vouchers' => true,
    ],

    /* Resources */

    'resources' => [
        'navigation_sort' => [
            'affiliates' => 60,
            'affiliate_conversions' => 61,
            'affiliate_payouts' => 62,
            'affiliate_programs' => 63,
            'affiliate_commission_templates' => 64,
            'affiliate_fraud_signals' => 65,
            'affiliate_links' => 66,
            'affiliate_touchpoints' => 67,
            'affiliate_ranks' => 68,
            'affiliate_network' => 69,
            'affiliate_rank_histories' => 70,
            'affiliate_support_tickets' => 71,
            'affiliate_tax_documents' => 72,
        ],
    ],

    'pages' => [
        'navigation_sort' => [
            'reports' => 10,
            'payout_batch' => 12,
            'fraud_review' => 15,
        ],
    ],
];
