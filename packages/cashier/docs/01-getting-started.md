# Getting Started

This guide will help you get started with the AIArmada Cashier package for multi-gateway billing.

## Prerequisites

Before you begin, ensure you have:

- PHP 8.2 or higher
- Laravel 12.0 or higher
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

**For Paddle:**
```bash
composer require laravel/cashier-paddle
```

### Step 3: Publish Configuration

```bash
php artisan vendor:publish --tag=cashier-config
```

This will create `config/cashier.php` with all gateway settings.

### Step 4: Run Migrations

```bash
php artisan vendor:publish --tag=cashier-migrations
php artisan migrate
```

This creates the following tables:
- `gateway_subscriptions` - Unified subscription storage
- `gateway_subscription_items` - Subscription line items

It also adds columns to your users table:
- `stripe_id` - Stripe customer ID
- `chip_id` - CHIP customer ID
- `trial_ends_at` - Generic trial end date

## Configuration

### Environment Setup

Add the following to your `.env` file:

```env
# Default Gateway
CASHIER_GATEWAY=stripe
CASHIER_CURRENCY=USD
CASHIER_CURRENCY_LOCALE=en_US

# Stripe Credentials
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# CHIP Credentials (if using CHIP)
CHIP_BRAND_ID=your_brand_id
CHIP_API_KEY=your_api_key
CHIP_WEBHOOK_KEY=your_webhook_key
```

### Model Setup

Add the `Billable` trait to your User model:

```php
<?php

namespace App\Models;

use AIArmada\Cashier\Billable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Billable;
    
    // ...
}
```

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

// Create a subscription on the default gateway (Stripe)
$subscription = $user->newSubscription('default', 'price_monthly')
    ->trialDays(14)
    ->create($paymentMethodId);

// Check if it was created successfully
if ($subscription->valid()) {
    echo "Subscription created successfully!";
}
```

### Processing a One-Time Payment

```php
// Charge $10.00
$payment = $user->charge(1000, $paymentMethodId);

if ($payment->isSuccessful()) {
    echo "Payment successful!";
} elseif ($payment->requiresAction()) {
    // Redirect user for 3D Secure
    return redirect($payment->actionUrl());
}
```

### Creating a Checkout Session

```php
$checkout = $user->checkout([
    ['price' => 'price_xxx', 'quantity' => 1],
    ['price' => 'price_yyy', 'quantity' => 2],
])
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
$stripeSubscription = $user->newSubscription('streaming', 'price_xxx', 'stripe')
    ->create();

// Create subscription on CHIP for local payments
$chipSubscription = $user->newSubscription('local-plan', 'plan_id', 'chip')
    ->create();

// Query subscriptions per gateway
$stripeSubscriptions = $user->subscriptions('stripe');
$chipSubscriptions = $user->subscriptions('chip');

// Get all subscriptions
$allSubscriptions = $user->subscriptions();
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

- [Subscriptions Guide](02-subscriptions.md) - Learn about managing subscriptions
- [Payments Guide](03-payments.md) - Handle one-time payments
- [Webhooks Guide](04-webhooks.md) - Set up webhook handling
- [Multi-Gateway Guide](05-multi-gateway.md) - Advanced multi-gateway usage
