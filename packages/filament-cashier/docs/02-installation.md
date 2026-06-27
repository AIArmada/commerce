---
title: Installation
---

# Installation

This guide walks through installing and configuring Filament Cashier for multi-gateway billing.

## Step 1: Install the Package

```bash
composer require aiarmada/filament-cashier
```

This automatically installs `aiarmada/cashier` as a dependency.

## Step 2: Install Gateway Packages

Install at least one gateway package:

**For Stripe:**
```bash
composer require laravel/cashier
```

**For CHIP:**
```bash
composer require aiarmada/cashier-chip
```

**For both:**
```bash
composer require laravel/cashier aiarmada/cashier-chip
```

## Step 3: Publish Configuration

```bash
php artisan vendor:publish --tag=filament-cashier-config
```

This creates `config/filament-cashier.php`.

## Step 4: Publish Translations (Optional)

```bash
php artisan vendor:publish --tag=filament-cashier-translations
```

Creates translation files in `lang/vendor/filament-cashier/`.

## Step 5: Run Gateway Migrations

Each gateway has its own migrations:

**For Stripe:**
```bash
php artisan vendor:publish --tag=cashier-migrations
php artisan migrate
```

**For CHIP:**
```bash
php artisan vendor:publish --tag=cashier-chip-migrations
php artisan migrate
```

## Step 6: Configure Model

Add the `Billable` trait to your User model (or any billable model):

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

### Understanding the Billable Contract

The `Billable` trait implements `AIArmada\Cashier\Contracts\BillableContract`, which provides a unified interface for multi-gateway billing operations. This contract includes:

#### Bridge API (Gateway-Agnostic)
These methods work consistently across all installed gateways:

- **Gateway Management**: `preferredGateway()`, `setPreferredGateway()`
- **Customer Management**: `createOrGetCustomer()`, `updateCustomer()`, `syncCustomer()`
- **Charging**: `chargeWithGateway($gateway, ...)`
- **Subscriptions**: `newGatewaySubscription($gateway, ...)`, `allGatewaySubscriptions()`, `subscribedViaGateway($gateway)`
- **Payment Methods**: `allGatewayPaymentMethods()`, `defaultGatewayPaymentMethod()`
- **Invoices**: `allGatewayInvoices()`, `gatewayBillingPortalUrl($gateway)`

#### Gateway-Native Hooks (Optional)
For advanced use cases, gateway implementations may call optional native methods on your billable model:

- **Stripe**: `createOrGetStripeCustomer()`, `syncStripeCustomerDetails()`
- **CHIP**: `createOrGetChipCustomer()`

These are accessed through gateway adapters, not directly on the billable interface.

### Multi-Gateway Usage

Once configured, you can use any installed gateway:

```php
$user = User::first();

// Use default gateway
$subscription = $user->newGatewaySubscription($user->preferredGateway(), 'price_123');

// Switch to a different gateway
$user->setPreferredGateway('chip');
$subscription = $user->newGatewaySubscription('chip', 'plan-id');

// Access all subscriptions across all gateways
$allSubscriptions = $user->allGatewaySubscriptions();

// Get payment methods from a specific gateway
$stripeMethods = $user->gatewayPaymentMethods('stripe');
$chipMethods = $user->gatewayPaymentMethods('chip');
```

## Step 7: Register the Plugin

Add the plugin to your Filament panel:

```php
<?php

namespace App\Providers\Filament;

use AIArmada\FilamentCashier\FilamentCashierPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugins([
                FilamentCashierPlugin::make(),
            ]);
    }
}
```

## Environment Configuration

### Stripe Credentials

```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

### CHIP Credentials

```env
CHIP_BRAND_ID=your_brand_id
CHIP_COLLECT_API_KEY=your_api_key
CHIP_WEBHOOK_SECRET=your_webhook_secret
```

### Default Gateway

```env
CASHIER_GATEWAY=stripe
CASHIER_CURRENCY=USD
CASHIER_CURRENCY_LOCALE=en_US
```

## Plugin Options

Configure the plugin with available options:

```php
FilamentCashierPlugin::make()
    // Navigation
    ->navigationGroup('Billing')
    ->navigationSort(50)
    
    // Features
    ->dashboard()           // Enable billing dashboard (default: true)
    ->subscriptions()       // Enable subscriptions resource (default: true)
    ->invoices()            // Enable invoices resource (default: true)
    ->gatewayManagement()   // Enable gateway management page (default: false)
    ->customerPortalMode()  // Enable customer portal mode (default: false)
```

### Disabling Features

```php
FilamentCashierPlugin::make()
    ->dashboard(false)      // Disable dashboard
    ->invoices(false)       // Disable invoices
```

## Customer Portal Setup

For a customer-facing billing portal, use the `BillingPanelProvider`:

```php
// config/filament-cashier.php
'billing_portal' => [
    'enabled' => true,
    'panel_id' => 'billing',
    'path' => 'billing',
    'brand_name' => 'My App Billing',
    'primary_color' => '#6366f1',
    'auth_guard' => 'web',
    'login_enabled' => true,
    'features' => [
        'subscriptions' => true,
        'payment_methods' => true,
        'invoices' => true,
    ],
],
```

Then register the panel provider:

```php
// config/app.php or bootstrap/providers.php
'providers' => [
    // ...
    AIArmada\FilamentCashier\CustomerPortal\BillingPanelProvider::class,
],
```

## Verify Installation

After installation, verify everything works:

1. Navigate to `/admin/billing-dashboard` - you should see the dashboard
2. Navigate to `/admin/subscriptions` - you should see the subscriptions list
3. Navigate to `/admin/invoices` - you should see the invoices list

If no gateways are installed, you'll see the Gateway Setup page with installation instructions.

## Troubleshooting

### "No gateways detected"

Install at least one gateway package:
```bash
composer require laravel/cashier
# or
composer require aiarmada/cashier-chip
```

### Dashboard shows no data

1. Ensure your models use the `Billable` trait
2. Verify gateway credentials are configured
3. Check that migrations have been run

### Missing translations

Publish the translation files:
```bash
php artisan vendor:publish --tag=filament-cashier-translations
```

### Permissions errors

Ensure your policies allow access. See the [Configuration](03-configuration.md) guide for policy customization.
