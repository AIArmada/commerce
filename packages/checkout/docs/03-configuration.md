---
title: Configuration
---

# Configuration

After publishing the config file, you can customize the checkout behavior.

## Configuration File

```php
// config/checkout.php
return [
    /*
    |--------------------------------------------------------------------------
    | Database Configuration
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
    | Default Settings
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'currency' => env('CHECKOUT_CURRENCY', 'MYR'),
        'session_ttl' => 60 * 60 * 24, // 24 hours
        'session_query_param' => 'session',
        'shipping_rate' => env('CHECKOUT_DEFAULT_SHIPPING_RATE', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    */
    'models' => [
        'customer' => \AIArmada\Customers\Models\Customer::class,
        'order' => \AIArmada\Orders\Models\Order::class,
    ],

    'transformers' => [
        'billing' => \AIArmada\Checkout\Transformers\NullSessionDataTransformer::class,
        'shipping' => \AIArmada\Checkout\Transformers\NullSessionDataTransformer::class,
    ],
    'create_order' => [
        'confirm_payment' => true,
    ],


    /*
    |--------------------------------------------------------------------------
    | Checkout Steps
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
    | Gateway-specific settings reference their respective package configs
    | to avoid configuration mismatches.
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
    'response_mode' => 'redirect',

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
    | Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'enabled' => env('CHECKOUT_ROUTES_ENABLED', true),
        'prefix' => env('CHECKOUT_ROUTE_PREFIX', 'checkout'),
        'middleware' => ['web'],
        'callbacks' => [
            'success' => 'payment/success',
            'failure' => 'payment/failure',
            'cancel' => 'payment/cancel',
        ],
        'webhook_prefix' => env('CHECKOUT_WEBHOOK_PREFIX', 'webhooks'),
        'webhook_path' => 'checkout',
        'webhook_middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirects (After Payment Callback)
    |--------------------------------------------------------------------------
    |
    | Supports placeholders: {order_id}, {session_id}
    |
    */
    'redirects' => [
        'success' => env('CHECKOUT_REDIRECT_SUCCESS', '/orders/{order_id}'),
        'failure' => env('CHECKOUT_REDIRECT_FAILURE', '/checkout/failed'),
        'cancel' => env('CHECKOUT_REDIRECT_CANCEL', '/checkout/cancelled'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Verification
    |--------------------------------------------------------------------------
    |
    | - CHIP: Uses config('chip.webhooks.verify_signature')
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
```

## Configuration Options

### Database Settings

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `database.table_prefix` | string | `''` | Prefix for database tables |
| `database.tables.checkout_sessions` | string | `checkout_sessions` | Sessions table name |
| `database.json_column_type` | string | `json` | JSON column type (`json` or `jsonb`) |

### Default Settings

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `defaults.currency` | string | `MYR` | Default currency for payments |
| `defaults.session_ttl` | int | `86400` | Session expiration in seconds |
| `defaults.session_query_param` | string | `session` | Query param for session ID in URLs |
| `defaults.shipping_rate` | int | `1000` | Fallback shipping rate in cents |

### Models

Customize model classes if you've extended the defaults:

```php
'models' => [
    'customer' => App\Models\Customer::class, // Your custom model
    'order' => App\Models\Order::class,
],
```

When using a custom order model, ensure your implementation of
`AIArmada\Orders\Contracts\OrderServiceInterface` returns that model and
binds the interface in the container.

### Session Data Transformers

Transform billing/shipping data before checkout steps use it:

```php
'transformers' => [
    'billing' => App\Checkout\Transformers\BillingTransformer::class,
    'shipping' => App\Checkout\Transformers\ShippingTransformer::class,
],
```

Each transformer must implement `AIArmada\Checkout\Contracts\SessionDataTransformerInterface`.

### Payment Settings

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `payment.default_gateway` | string | `chip` | Default payment gateway |
| `payment.gateway_priority` | array | `['chip', 'cashier-chip', 'cashier']` | Gateway resolution order |
| `payment.retry_limit` | int | `3` | Max payment retry attempts |

The `payment.gateways.*.enabled` flags are config constants in the package config (all `true` by default), not environment-driven toggles.

Gateway-specific configuration references the related package configs:
- **CHIP** (`chip`): Uses `config('chip.*')`
- **Cashier CHIP** (`cashier-chip`): Uses `config('cashier-chip.*')`
- **Cashier** (`cashier`): Uses `config('cashier.*')`

### Routes

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `routes.enabled` | bool | `true` | Enable/disable checkout routes |
| `routes.prefix` | string | `checkout` | Route prefix |
| `routes.callbacks.*` | string | `payment/*` | Payment callback paths |
| `routes.webhook_prefix` | string | `webhooks` | Webhook route prefix |

### Response Mode and Views

```php
'response_mode' => 'redirect', // 'redirect' or 'view'

'views' => [
    'enabled' => true,
    'layout' => 'layouts.app',
    'routes' => [
        'success' => 'checkout::success',
        'failure' => 'checkout::failure',
        'cancel' => 'checkout::cancel',
    ],
],
```

- `response_mode='redirect'` redirects users using `redirects.*`.
- `response_mode='view'` renders checkout package views directly.

### Redirects

Configure where users are sent after payment:

```php
'redirects' => [
    'success' => '/orders/{order_id}', // Supports {order_id}, {session_id}
    'failure' => '/checkout/failed',
    'cancel' => '/checkout/cancelled',
],
```

### Webhook Verification

Webhooks are verified using the source gateway's mechanism:

```php
'webhooks' => [
    'verify_signature' => true, // Enforce signature verification
    'log_channel' => null,      // Optional dedicated log channel
],
```

`webhooks.verify_signature` should remain enabled in production. If disabled, checkout rejects webhook requests in production environments.

### Integration Settings

Control which integrations are active:

```php
'integrations' => [
    'inventory' => [
        'enabled' => true,
        'validate_stock' => true,
        'reserve_before_payment' => true,
        'reservation_ttl' => 900,
    ],
    'shipping' => [
        'enabled' => true,
        'require_selection' => true,
        'jnt' => ['enabled' => true, 'auto_detect' => true],
    ],
    'tax' => ['enabled' => true],
    'promotions' => ['enabled' => true, 'auto_apply' => true],
    'vouchers' => ['enabled' => true, 'allow_multiple' => false],
],
```

### Owner Settings

Enable multi-tenancy:

```php
'owner' => [
    'enabled' => true,
    'include_global' => false,
    'auto_assign_on_create' => true,
],
```

## Environment Variables

```env
# Currency
CHECKOUT_CURRENCY=MYR

# Payment gateway
CHECKOUT_DEFAULT_GATEWAY=chip

# Routes
CHECKOUT_ROUTES_ENABLED=true
CHECKOUT_ROUTE_PREFIX=checkout

# Redirects
CHECKOUT_REDIRECT_SUCCESS=/orders/{order_id}
CHECKOUT_REDIRECT_FAILURE=/checkout/failed

# Webhooks
CHECKOUT_WEBHOOK_VERIFY_SIGNATURE=true
CHECKOUT_WEBHOOK_LOG_CHANNEL=

# Multi-tenancy
CHECKOUT_OWNER_ENABLED=false

# Documents
CHECKOUT_DOCUMENTS_QUEUE=default
```
