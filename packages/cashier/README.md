# AIArmada Cashier

A unified multi-gateway billing integration for Laravel supporting Stripe and CHIP.

## Introduction

AIArmada Cashier provides a unified interface for working with multiple payment gateways in Laravel. Instead of learning different APIs for Stripe and CHIP, you can use a single, consistent API that works across all supported gateways.

### Key Features

- **Unified API**: One interface for multiple gateways
- **Multi-Gateway Support**: Users can have subscriptions on different gateways simultaneously
- **Gateway Manager**: Factory pattern for resolving gateways dynamically
- **Contract-First Design**: All components implement well-defined interfaces
- **No Data Duplication**: Subscriptions stored in respective gateway tables

### Architecture

This package is a **wrapper/adapter layer** that delegates to underlying gateway packages:

- `laravel/cashier` → Stripe subscriptions stored in `subscriptions` table
- `aiarmada/cashier-chip` → CHIP subscriptions stored in `chip_subscriptions` table

**No additional tables are created.** This package provides a unified interface only.

## Requirements

- PHP 8.2+
- Laravel 12.0+
- At least one gateway package installed:
  - `laravel/cashier` for Stripe
  - `aiarmada/cashier-chip` for CHIP

## Installation

```bash
composer require aiarmada/cashier
```

Install gateway packages as needed:

```bash
# For Stripe
composer require laravel/cashier

# For CHIP
composer require aiarmada/cashier-chip
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=cashier-config
```

## Configuration

### Environment Variables

```env
# Default gateway
CASHIER_GATEWAY=stripe

# Stripe Configuration (if using laravel/cashier)
STRIPE_KEY=pk_live_xxx
STRIPE_SECRET=sk_live_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx

# CHIP Configuration (if using cashier-chip)
CHIP_BRAND_ID=your_brand_id
CHIP_API_KEY=your_api_key
CHIP_WEBHOOK_KEY=your_webhook_key

# Currency Settings
CASHIER_CURRENCY=USD
CASHIER_CURRENCY_LOCALE=en_US
```

## Usage

### Setting Up the Billable Model

Add **all** billable traits to your User model:

```php
use AIArmada\Cashier\Billable as CashierBillable;
use Laravel\Cashier\Billable as StripeBillable;
use AIArmada\CashierChip\Billable as ChipBillable;

class User extends Authenticatable
{
    use StripeBillable, ChipBillable, CashierBillable;
}
```

### Gateway Selection

```php
use AIArmada\Cashier\Cashier;

// Get the default gateway
$gateway = Cashier::gateway();

// Get a specific gateway
$stripeGateway = Cashier::gateway('stripe');
$chipGateway = Cashier::gateway('chip');

// From a billable model
$user->gateway();        // Default gateway
$user->gateway('chip');  // Specific gateway
```

### Creating Subscriptions

```php
// Via default gateway
$user->newGatewaySubscription('default', 'price_xxx')->create();

// Via specific gateway
$user->newGatewaySubscription('default', 'price_xxx', 'chip')->create();

// Or use the gateway directly
$user->gateway('stripe')->subscription($user, 'default', 'price_xxx')->create();
```

### Querying Subscriptions

```php
// Get all subscriptions across all gateways
$allSubscriptions = $user->allSubscriptions();

// Find a subscription by type from any gateway
$subscription = $user->findSubscription('default');

// Get subscriptions for a specific gateway
$stripeSubscriptions = $user->gatewaySubscriptions('stripe');
$chipSubscriptions = $user->gatewaySubscriptions('chip');

// Check if subscribed on any gateway
if ($user->subscribedOnAny('premium')) {
    // Has premium subscription on Stripe OR CHIP
}

// Check if subscribed on specific gateway
if ($user->subscribedViaGateway('premium', null, 'stripe')) {
    // Has premium subscription on Stripe
}
```

### One-Time Charges

```php
// Charge via default gateway
$payment = $user->chargeWithGateway(1000, 'pm_xxx');

// Charge via specific gateway
$payment = $user->chargeWithGateway(5000, 'pm_xxx', 'chip');
```

### Payment Methods

```php
// Get payment methods from all gateways
$allMethods = $user->allGatewayPaymentMethods();

// Get payment methods from specific gateway
$stripeMethods = $user->gatewayPaymentMethods('stripe');

// Get default payment method for a gateway
$default = $user->defaultGatewayPaymentMethod('chip');
```

### Invoices

```php
// Get invoices from all gateways
$allInvoices = $user->allGatewayInvoices();

// Get invoices from specific gateway
$stripeInvoices = $user->gatewayInvoices('stripe');
```

## Extending with Custom Gateways

```php
use AIArmada\Cashier\Gateways\AbstractGateway;

class PayPalGateway extends AbstractGateway
{
    public function name(): string
    {
        return 'paypal';
    }
    
    // Implement required methods...
}

// Register in a service provider
Cashier::manager()->extend('paypal', function ($app) {
    return new PayPalGateway(config('cashier.gateways.paypal'));
});
```

## License

MIT License. See [LICENSE](LICENSE) for details.
