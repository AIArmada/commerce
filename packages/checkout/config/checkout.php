<?php

declare(strict_types=1);
use AIArmada\Checkout\Transformers\NullSessionDataTransformer;
use AIArmada\Customers\Models\Customer;
use AIArmada\Orders\Models\Order;

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => env('CHECKOUT_TABLE_PREFIX', env('COMMERCE_TABLE_PREFIX', '')),
        'tables' => [
            'checkout_sessions' => 'checkout_sessions',
        ],
        'json_column_type' => env('CHECKOUT_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'currency' => env('CHECKOUT_CURRENCY', 'MYR'),
        'session_ttl' => 60 * 60 * 24, // 24 hours
        'session_query_param' => 'session', // Query param name for session ID
        'shipping_rate' => env('CHECKOUT_DEFAULT_SHIPPING_RATE', 1000), // Fallback shipping rate in cents
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Customize the model classes used by the checkout package. This allows
    | you to extend the default models with your own implementations.
    |
    */
    'models' => [
        'customer' => Customer::class,
        'order' => Order::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Data Transformers
    |--------------------------------------------------------------------------
    */
    'transformers' => [
        'billing' => NullSessionDataTransformer::class,
        'shipping' => NullSessionDataTransformer::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Steps
    |--------------------------------------------------------------------------
    */
    'steps' => [
        'enabled' => [
            'validate_cart' => true,
            'resolve_customer' => true,
            'calculate_pricing' => true,
            'apply_discounts' => true,
            'calculate_shipping' => true,
            'calculate_tax' => true,
            'reserve_inventory' => true,
            'process_payment' => true,
            'create_order' => true,
            'dispatch_documents' => true,
        ],
        'order' => [
            'validate_cart',
            'resolve_customer',
            'calculate_pricing',
            'apply_discounts',
            'calculate_shipping',
            'calculate_tax',
            'reserve_inventory',
            'process_payment',
            'create_order',
            'dispatch_documents',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Create Order Step Settings
    |--------------------------------------------------------------------------
    |
    | Configure how the create_order step behaves after order creation.
    |
    | confirm_payment: When true, triggers PaymentConfirmed transition on the
    |                  order after creation. This creates a Payment record and
    |                  dispatches the OrderPaid event (for notifications, etc).
    |
    */
    'create_order' => [
        'confirm_payment' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Owner / Multi-tenancy
    |--------------------------------------------------------------------------
    */
    'owner' => [
        'enabled' => env('CHECKOUT_OWNER_ENABLED', false),
        'include_global' => false,
        'auto_assign_on_create' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    */
    'integrations' => [
        'inventory' => [
            'enabled' => true,
            'validate_stock' => true,
            'reserve_before_payment' => true,
            'release_on_failure' => true,
            'reservation_ttl' => 60 * 15, // 15 minutes
        ],
        'shipping' => [
            'enabled' => true,
            'require_selection' => true,
            'jnt' => [
                'enabled' => true,
                'auto_detect' => true,
            ],
        ],
        'tax' => [
            'enabled' => true,
        ],
        'promotions' => [
            'enabled' => true,
            'auto_apply' => true,
        ],
        'vouchers' => [
            'enabled' => true,
            'allow_multiple' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    |
    | Configure payment gateway integration. When using 'chip' or 'jnt', the
    | checkout package references their respective package configs directly
    | to avoid configuration mismatches.
    |
    | Gateway options: 'chip', 'stripe', 'cashier', 'cashier-chip'
    |
    */
    'payment' => [
        'default_gateway' => env('CHECKOUT_DEFAULT_GATEWAY', 'chip'),
        'gateway_priority' => ['chip', 'cashier-chip', 'cashier'],
        'retry_limit' => 3,

        'gateways' => [
            'cashier' => [
                'enabled' => true,
            ],
            'cashier-chip' => [
                'enabled' => true,
            ],
            'chip' => [
                'enabled' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Configure all checkout routes. Callback routes handle user redirects
    | from payment gateways. Webhook routes handle async notifications.
    |
    */
    'routes' => [
        'enabled' => env('CHECKOUT_ROUTES_ENABLED', true),
        'prefix' => env('CHECKOUT_ROUTE_PREFIX', 'checkout'),
        'middleware' => ['web'],

        // Payment callback routes (user redirects from gateway)
        'callbacks' => [
            'success' => 'payment/success',
            'failure' => 'payment/failure',
            'cancel' => 'payment/cancel',
        ],

        // Webhook configuration
        'webhook_prefix' => env('CHECKOUT_WEBHOOK_PREFIX', 'webhooks'),
        'webhook_path' => 'checkout',
        'webhook_middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirects (After Payment Callback)
    |--------------------------------------------------------------------------
    |
    | Where to redirect users after payment callbacks. Supports placeholders:
    | {order_id}, {session_id}
    |
    */
    'redirects' => [
        'success' => env('CHECKOUT_REDIRECT_SUCCESS', '/orders/{order_id}'),
        'failure' => env('CHECKOUT_REDIRECT_FAILURE', '/checkout/failed'),
        'cancel' => env('CHECKOUT_REDIRECT_CANCEL', '/checkout/cancelled'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Mode
    |--------------------------------------------------------------------------
    |
    | How the package responds after processing payment callbacks:
    |
    | 'redirect' - Redirect to URLs in 'redirects' config (default)
    |              Use this when you have custom result page controllers.
    |
    | 'view' - Render package views directly
    |          Use this for simpler setups without custom controllers.
    |
    */
    'response_mode' => 'redirect',

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    |
    | Configure package views for checkout result pages.
    | Views can be published with: php artisan vendor:publish --tag=checkout-views
    |
    | Only used when response_mode is 'view'.
    |
    */
    'views' => [
        'enabled' => true,
        'layout' => 'layouts.app',
        'routes' => [
            'success' => 'checkout::success',
            'failure' => 'checkout::failure',
            'cancel' => 'checkout::cancel',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Verification
    |--------------------------------------------------------------------------
    |
    | Webhook signature verification settings. When enabled, webhooks are
    | validated using the source gateway's verification mechanism:
    | - CHIP: Uses config('chip.webhooks.verify_signature') and public key
    | - Stripe: Uses config('cashier.webhook.secret')
    |
    */
    'webhooks' => [
        'verify_signature' => env('CHECKOUT_WEBHOOK_VERIFY_SIGNATURE', true),
        'log_channel' => env('CHECKOUT_WEBHOOK_LOG_CHANNEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    */
    'documents' => [
        'queue' => env('CHECKOUT_DOCUMENTS_QUEUE', 'default'),
        'generate_invoice' => true,
        'generate_receipt' => true,
    ],
];
