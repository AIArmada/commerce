---
title: Installation
---

# Installation

This guide installs `aiarmada/cashier` and the gateway packages it coordinates for multi-gateway billing.

## Prerequisites

Before you begin, ensure you have:

- PHP 8.4 or higher
- Laravel 13.0 or higher
- Composer installed
- At least one payment gateway account (Stripe, CHIP, etc.)

## Installation

### Step 1: Install the Package

```bash
composer require aiarmada/cashier
```

### Step 2: Install Gateway Packages

Install the gateway packages for the payment providers you want to use:

**For Stripe:**
```bash
composer require laravel/cashier
```

**For CHIP:**
```bash
composer require aiarmada/cashier-chip
```

### Step 3: Publish Configuration

```bash
php artisan vendor:publish --tag=cashier-config
```

This will create `config/cashier.php` with all gateway settings.

### Step 4: Run Gateway Migrations

`aiarmada/cashier` is a wrapper layer only. It does **not** create its own unified
subscription tables.

Instead:

- `laravel/cashier` owns Stripe tables such as `subscriptions` and `subscription_items`
- `aiarmada/cashier-chip` owns CHIP tables such as `chip_subscriptions` and `chip_subscription_items`

For Stripe, you may publish the vendor migrations when you need to customize them:

```bash
php artisan vendor:publish --tag=cashier-stripe-migrations
php artisan migrate
```

If you do not publish them, `aiarmada/cashier` will conditionally auto-load Laravel Cashier's
vendor migrations as a fallback when `laravel/cashier` is installed.

For CHIP, install `aiarmada/cashier-chip` and run your normal migrations:

```bash
php artisan migrate
```

That gives you gateway-owned billable columns such as:

- `stripe_id` from `laravel/cashier`
- `chip_id` from `aiarmada/cashier-chip`
- gateway-specific subscription tables from the installed gateway packages

## Configuration

### Environment Setup

Add the following to your `.env` file:

```env
# Default Gateway
CASHIER_GATEWAY=stripe
CASHIER_CURRENCY=USD
CASHIER_LOCALE=en
CASHIER_STRIPE_CURRENCY_LOCALE=en_US
CASHIER_CHIP_CURRENCY_LOCALE=ms_MY

# Stripe Credentials
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# CHIP Credentials (if using CHIP)
CHIP_BRAND_ID=your_brand_id
CHIP_COLLECT_API_KEY=your_api_key
CHIP_WEBHOOK_SECRET=your_webhook_secret
```

### Model Setup

Add the wrapper trait **and** the traits from the gateway packages you install:

```php
<?php

namespace App\Models;

use AIArmada\Cashier\Billable as CashierBillable;
use AIArmada\CashierChip\Billable as ChipBillable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Cashier\Billable as StripeBillable;

class User extends Authenticatable
{
    use StripeBillable, ChipBillable, CashierBillable;
    
    // ...
}
```

Only include the gateway-specific traits for the gateways you actually install.

### Register Custom Customer Model (Optional)

If you're using a custom model, register it in a service provider:

```php
use AIArmada\Cashier\Cashier;

public function boot()
{
    Cashier::useCustomerModel(\App\Models\Customer::class);
}
```

## Quick Start Examples

### Creating Your First Subscription

```php
use App\Models\User;

$user = User::find(1);

// Create a subscription on the default configured gateway
$subscription = $user->newGatewaySubscription('default', 'price_monthly')
    ->trialDays(14)
    ->create($paymentMethodId);

// Check if it was created successfully
if ($subscription->valid()) {
    echo "Subscription created successfully!";
}
```

### Processing a One-Time Payment

```php
// Charge $10.00 on the default configured gateway
$payment = $user->chargeWithGateway(1000, $paymentMethodId);

if ($payment->isSuccessful()) {
    echo "Payment successful!";
} elseif ($payment->requiresAction()) {
    // Redirect user for 3D Secure
    return redirect($payment->actionUrl());
}
```

### Creating a Checkout Session

```php
$checkout = $user->checkoutWithGateway('stripe')
    ->price('price_xxx')
    ->price('price_yyy', 2)
    ->successUrl(route('checkout.success'))
    ->cancelUrl(route('checkout.cancel'))
    ->create();

// Redirect to hosted checkout
return redirect($checkout->url());
```

## Using Multiple Gateways

One of the key features is the ability to use multiple gateways:

```php
// Create subscription on Stripe
$stripeSubscription = $user->newGatewaySubscription('streaming', 'price_xxx', 'stripe')
    ->create();

// Create subscription on CHIP for local payments
$chipSubscription = $user->newGatewaySubscription('local-plan', 'plan_id', 'chip')
    ->create();

// Query subscriptions per gateway
$stripeSubscriptions = $user->gatewaySubscriptions('stripe');
$chipSubscriptions = $user->gatewaySubscriptions('chip');

// Get all subscriptions
$allSubscriptions = $user->allSubscriptions();
```

### CHIP Subscription Scheduler

> **Important:** CHIP doesn't have native subscriptions. Your app must schedule renewals.

Add this to your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // Process CHIP subscription renewals every hour
    $schedule->command('cashier-chip:renew-subscriptions')
        ->hourly()
        ->withoutOverlapping();
}
```

This is not needed for Stripe - Stripe handles subscription renewals automatically.

## Next Steps

- [Configuration Guide](03-configuration.md) - Review the published config and cart integration toggles
- [Usage Guide](04-usage.md) - Start with the canonical entry point for unified billing flows
- [Subscriptions Guide](05-subscriptions.md) - Learn about managing subscriptions
- [Payments Guide](06-payments.md) - Handle one-time payments
- [Multi-Gateway Guide](07-multi-gateway.md) - Advanced multi-gateway usage
- [Webhooks Guide](08-webhooks.md) - Set up webhook handling
