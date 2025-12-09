<?php

declare(strict_types=1);

return [
    'title' => 'Gateway Management',

    'setup' => [
        'title' => 'Gateway Setup Required',
        'description' => 'No payment gateways are currently configured. Install at least one gateway package to start accepting payments.',
        'no_gateway_title' => 'No Gateways Available',
        'no_gateway_description' => 'Install at least one payment gateway to continue.',
        'stripe' => [
            'name' => 'Stripe',
            'description' => 'Accept credit cards, ACH, and international payments.',
            'install' => 'composer require laravel/cashier',
        ],
        'chip' => [
            'name' => 'CHIP',
            'description' => 'Accept FPX, e-wallets, and Malaysian payments.',
            'install' => 'composer require aiarmada/cashier-chip',
        ],
    ],

    'management' => [
        'title' => 'Gateway Management',
        'navigation' => 'Gateway Management',
        'health_title' => 'Gateway Health Status',
        'health_description' => 'Monitor the health and connectivity of your payment gateways.',
        'last_check' => 'Last checked',
        'default_gateway' => 'Default Gateway',
        'config_title' => 'Configuration Guide',
        'config_description' => 'Environment variables required for each gateway.',
        'stripe_title' => 'Stripe Configuration',
        'stripe_key_desc' => 'Your Stripe publishable key',
        'stripe_secret_desc' => 'Your Stripe secret key',
        'stripe_webhook_desc' => 'Your Stripe webhook signing secret',
        'chip_title' => 'CHIP Configuration',
        'chip_brand_desc' => 'Your CHIP brand ID',
        'chip_api_desc' => 'Your CHIP API key',
        'chip_webhook_desc' => 'Your CHIP webhook verification token',
        'features_title' => 'Gateway Features Comparison',
        'feature' => 'Feature',
    ],

    'status' => [
        'title' => 'Gateway Status',
        'available' => 'Available',
        'unavailable' => 'Not Installed',
        'default' => 'Default',
    ],

    'health' => [
        'title' => 'Gateway Health',
        'healthy' => 'Healthy',
        'degraded' => 'Degraded',
        'down' => 'Down',
        'error' => 'Error',
        'unknown' => 'Unknown',
        'not_configured' => 'Not Configured',
        'sdk_missing' => 'SDK not installed',
        'connection_error' => 'Connection failed. Please check your configuration.',
        'last_checked' => 'Last checked',
    ],

    'fields' => [
        'gateway' => 'Gateway',
    ],

    'actions' => [
        'test_connection' => 'Test Connection',
        'set_default' => 'Set Default Gateway',
        'configure' => 'Configure',
    ],

    'notifications' => [
        'connection_success' => ':gateway connection successful',
        'connection_failed' => ':gateway connection failed',
        'default_set' => ':gateway set as default gateway',
    ],
];
