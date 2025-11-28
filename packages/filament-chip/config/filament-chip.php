<?php

declare(strict_types=1);

return [
    'navigation_group' => 'CHIP Operations',

    'navigation_badge_color' => 'primary',

    'polling_interval' => '45s',

    'resources' => [
        'navigation_sort' => [
            'purchases' => 10,
            'payments' => 20,
            'clients' => 30,
            'bank_accounts' => 40,
            'webhooks' => 50,
            'send_instructions' => 60,
            'send_limits' => 70,
            'send_webhooks' => 80,
            'company_statements' => 90,
        ],
    ],

    'tables' => [
        'created_on_format' => 'Y-m-d H:i:s',
        'amount_precision' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing Portal Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the customer-facing billing portal. This creates a separate
    | Filament panel where customers can manage their subscriptions, payment
    | methods, and view billing history.
    |
    */

    'billing' => [
        // Enable or disable the billing portal
        'enabled' => env('CHIP_BILLING_PORTAL_ENABLED', true),

        // Panel ID for the billing portal
        'panel_id' => 'billing',

        // Path prefix for the billing portal (e.g., /billing)
        'path' => 'billing',

        // Brand name displayed in the portal
        'brand_name' => env('CHIP_BILLING_BRAND_NAME', 'Billing Portal'),

        // Primary color for the portal theme
        'primary_color' => env('CHIP_BILLING_PRIMARY_COLOR', '#6366f1'),

        // Enable login page for the billing portal
        'login_enabled' => env('CHIP_BILLING_LOGIN_ENABLED', true),

        // Authentication guard for the billing portal
        'auth_guard' => 'web',

        // Allowed roles for accessing the billing portal (empty = all authenticated users)
        'allowed_roles' => [],

        // The billable model (user or team)
        'billable_model' => null, // e.g., App\Models\User::class

        // Features to enable in the billing portal
        'features' => [
            'subscriptions' => true,
            'payment_methods' => true,
            'invoices' => true,
        ],

        // Redirect URLs after actions
        'redirects' => [
            'after_payment_method_added' => null,
            'after_subscription_cancelled' => null,
        ],

        // Invoice configuration
        'invoice' => [
            'vendor_name' => null, // Falls back to config('app.name')
            'product_name' => 'Subscription',
        ],
    ],
];
