---
title: Installation
---

# Installation

## Requirements

- PHP 8.2+
- Laravel 11+
- CHIP Account with API credentials

## Installing the Package

```bash
composer require aiarmada/cashier-chip
```

## Configuration

### Publish Configuration

```bash
php artisan vendor:publish --tag=cashier-chip-config
```

This creates `config/cashier-chip.php`:

```php
return [
    // Database
    'database' => [
        'table_prefix' => env('CASHIER_CHIP_TABLE_PREFIX', 'cashier_chip_'),
        'json_column_type' => env('CASHIER_CHIP_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => [
            'subscriptions' => 'cashier_chip_subscriptions',
            'subscription_items' => 'cashier_chip_subscription_items',
        ],
    ],

    // Defaults
    'currency' => env('CASHIER_CHIP_CURRENCY', 'MYR'),
    'currency_locale' => env('CASHIER_CHIP_CURRENCY_LOCALE', 'ms_MY'),

    // Owner scope behavior
    'features' => [
        'owner' => [
            'enabled' => env('CASHIER_CHIP_OWNER_ENABLED', true),
            'include_global' => env('CASHIER_CHIP_OWNER_INCLUDE_GLOBAL', false),
            'auto_assign_on_create' => env('CASHIER_CHIP_OWNER_AUTO_ASSIGN_ON_CREATE', true),
            'validate_billable_owner' => env('CASHIER_CHIP_OWNER_VALIDATE_BILLABLE_OWNER', true),
        ],
    ],

    // Subscription behavior
    'subscriptions' => [
        'retry_days' => env('CASHIER_CHIP_RETRY_DAYS', 3),
        'max_retries' => env('CASHIER_CHIP_MAX_RETRIES', 3),
        'grace_days' => env('CASHIER_CHIP_GRACE_DAYS', 7),
    ],

    // Webhook route prefix
    'path' => env('CASHIER_CHIP_PATH', 'chip'),

    // Webhook verification
    'webhooks' => [
        'secret' => env('CHIP_WEBHOOK_SECRET'),
        'verify_signature' => env('CHIP_WEBHOOK_VERIFY_SIGNATURE', true),
    ],
];
```

### Environment Variables

Add to your `.env` file:

```env
# CHIP API Credentials
CHIP_BRAND_ID=your-brand-id
CHIP_COLLECT_API_KEY=your-collect-api-key
CHIP_WEBHOOK_SECRET=your-webhook-secret

# Cashier CHIP defaults
CASHIER_CHIP_CURRENCY=MYR
CASHIER_CHIP_CURRENCY_LOCALE=ms_MY
CASHIER_CHIP_OWNER_ENABLED=true
```

### Run Migrations

```bash
php artisan vendor:publish --tag=cashier-chip-migrations
php artisan migrate
```

This creates the following tables by default:

- `cashier_chip_subscriptions` - Subscription records
- `cashier_chip_subscription_items` - Subscription line items

## Billable Model

Add the `Billable` trait to your User model:

```php
<?php

namespace App\Models;

use AIArmada\CashierChip\Billable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Billable;
}
```

The `Billable` trait provides:

- Customer management (CHIP client creation)
- Payment method handling (recurring tokens)
- Checkout sessions
- Subscription management
- One-off charges

## Custom Customer Model

If you're using a different model for billing:

```php
// In AppServiceProvider::boot()
use AIArmada\CashierChip\CashierChip;

CashierChip::useCustomerModel(Team::class);
```

## Webhook Route

The package automatically registers a webhook route at:

```
POST /chip/webhook
```

Configure your CHIP dashboard to send webhooks to this URL.

### CSRF Protection

Exclude the webhook route from CSRF verification in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'chip/*',
    ]);
})
```

## Next Steps

- [Customer Management](customers.md) - Create and manage CHIP customers
- [One-off Charges](charges.md) - Process single payments
- [Checkout Sessions](checkout.md) - Redirect to hosted checkout
