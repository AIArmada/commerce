# AIArmada Cashier

A unified multi-gateway billing integration for Laravel supporting Stripe, CHIP, Paddle, and more.

## Table of Contents

- [Introduction](#introduction)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Gateway Selection](#gateway-selection)
  - [Customer Management](#customer-management)
  - [Subscriptions](#subscriptions)
  - [One-Time Charges](#one-time-charges)
  - [Payment Methods](#payment-methods)
  - [Checkout Sessions](#checkout-sessions)
  - [Invoices](#invoices)
- [Multi-Gateway Subscriptions](#multi-gateway-subscriptions)
- [Events](#events)
- [Webhooks](#webhooks)
- [Testing](#testing)
- [API Reference](#api-reference)

## Introduction

AIArmada Cashier provides a unified interface for working with multiple payment gateways in Laravel. Instead of learning different APIs for Stripe, CHIP, or Paddle, you can use a single, consistent API that works across all supported gateways.

### Key Features

- **Unified API**: One interface to rule them all
- **Multi-Gateway Subscriptions**: Users can have subscriptions on different gateways simultaneously
- **Gateway Manager**: Factory pattern for resolving gateways dynamically
- **Contract-First Design**: All components implement well-defined interfaces
- **Event System**: Standardized events across all gateways
- **Extensible**: Easily add custom gateways

## Requirements

- PHP 8.2+
- Laravel 12.0+
- At least one gateway package installed:
  - `laravel/cashier` for Stripe
  - `aiarmada/cashier-chip` for CHIP
  - `laravel/cashier-paddle` for Paddle

## Installation

```bash
composer require aiarmada/cashier
```

Publish the configuration and migrations:

```bash
php artisan vendor:publish --tag=cashier-config
php artisan vendor:publish --tag=cashier-migrations
php artisan migrate
```

## Configuration

### Environment Variables

```env
# Default gateway
CASHIER_GATEWAY=stripe

# Stripe Configuration
STRIPE_KEY=pk_live_xxx
STRIPE_SECRET=sk_live_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx

# CHIP Configuration
CHIP_BRAND_ID=your_brand_id
CHIP_API_KEY=your_api_key
CHIP_WEBHOOK_KEY=your_webhook_key

# Currency Settings
CASHIER_CURRENCY=USD
CASHIER_CURRENCY_LOCALE=en_US
```

### Configuration File

The configuration file `config/cashier.php` contains all gateway settings:

```php
return [
    'default' => env('CASHIER_GATEWAY', 'stripe'),

    'gateways' => [
        'stripe' => [
            'driver' => 'stripe',
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'currency' => env('CASHIER_CURRENCY', 'USD'),
        ],

        'chip' => [
            'driver' => 'chip',
            'brand_id' => env('CHIP_BRAND_ID'),
            'api_key' => env('CHIP_API_KEY'),
            'webhook_key' => env('CHIP_WEBHOOK_KEY'),
            'currency' => env('CASHIER_CURRENCY', 'MYR'),
        ],
    ],

    'models' => [
        'customer' => 'App\\Models\\User',
        'subscription' => \AIArmada\Cashier\Models\Subscription::class,
        'subscription_item' => \AIArmada\Cashier\Models\SubscriptionItem::class,
    ],
];
```

## Usage

### Setting Up the Billable Model

Add the `Billable` trait to your User model:

```php
use AIArmada\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

### Gateway Selection

Access gateways through the `Cashier` facade or the `Billable` trait:

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

### Customer Management

```php
// Create or get customer on the default gateway
$customer = $user->createAsCustomer([
    'name' => $user->name,
    'email' => $user->email,
]);

// Create customer on a specific gateway
$customer = $user->gateway('chip')->createCustomer($user, [
    'phone' => $user->phone,
]);

// Get customer IDs for all gateways
$ids = $user->gatewayIds();
// ['stripe' => 'cus_xxx', 'chip' => 'cus_yyy']

// Get customer ID for specific gateway
$stripeId = $user->gatewayId('stripe');
```

### Subscriptions

#### Creating Subscriptions

```php
// Create a subscription on the default gateway
$subscription = $user->newSubscription('default', 'price_monthly')
    ->trialDays(14)
    ->create($paymentMethod);

// Create a subscription on a specific gateway
$subscription = $user->newSubscription('default', 'price_id', 'chip')
    ->quantity(2)
    ->create();
```

#### Managing Subscriptions

```php
// Get a subscription
$subscription = $user->subscription('default');

// Check subscription status
$subscription->valid();        // Active, on trial, or on grace period
$subscription->active();       // Currently active
$subscription->onTrial();      // Currently on trial
$subscription->canceled();     // Has been canceled
$subscription->onGracePeriod(); // Canceled but still accessible
$subscription->ended();        // Fully ended

// Cancel subscription
$subscription->cancel();        // At period end
$subscription->cancelNow();     // Immediately
$subscription->cancelAt($date); // At specific date

// Resume a canceled subscription
$subscription->resume();

// Swap to a different plan
$subscription->swap('price_yearly');

// Update quantity
$subscription->updateQuantity(5);
$subscription->incrementQuantity();
$subscription->decrementQuantity();
```

#### Checking Subscription Status

```php
// Check if subscribed to any plan
if ($user->subscribed()) {
    // User has an active subscription
}

// Check specific subscription type
if ($user->subscribed('premium')) {
    // User has an active premium subscription
}

// Check if on trial
if ($user->onTrial()) {
    // User is on trial (model-level or subscription-level)
}
```

### One-Time Charges

```php
// Charge using the default gateway
$payment = $user->charge(1000, 'pm_xxx'); // $10.00

// Charge using a specific gateway
$payment = $user->charge(5000, 'pm_xxx', [
    'gateway' => 'chip',
    'description' => 'One-time purchase',
]);

// Check payment status
if ($payment->isSuccessful()) {
    // Payment completed
}

if ($payment->requiresAction()) {
    // Redirect to $payment->actionUrl()
}
```

### Payment Methods

```php
// Get all payment methods
$paymentMethods = $user->paymentMethods();

// Add a payment method
$user->addPaymentMethod('pm_xxx');

// Set default payment method
$user->updateDefaultPaymentMethod('pm_xxx');

// Delete payment methods
$user->deletePaymentMethods();
```

### Checkout Sessions

```php
// Create a checkout session
$checkout = $user->checkout([
    ['price' => 'price_xxx', 'quantity' => 1],
])
    ->successUrl(route('checkout.success'))
    ->cancelUrl(route('checkout.cancel'))
    ->create();

// Redirect to checkout
return redirect($checkout->url());

// On a specific gateway
$checkout = $user->checkout([...], 'chip')
    ->successUrl(route('checkout.success'))
    ->create();
```

### Invoices

```php
// Get all invoices
$invoices = $user->invoices();

// Get invoices including pending
$invoices = $user->invoicesIncludingPending();

// Find a specific invoice
$invoice = $user->findInvoice('in_xxx');

// Download invoice PDF
return $invoice->download();
```

## Multi-Gateway Subscriptions

The unified Cashier package allows users to have subscriptions on different gateways simultaneously:

```php
// Create a Stripe subscription
$stripeSubscription = $user->newSubscription('streaming', 'price_netflix', 'stripe')
    ->create();

// Create a CHIP subscription for local payments
$chipSubscription = $user->newSubscription('local-service', 'price_local', 'chip')
    ->create();

// Query subscriptions by gateway
$stripeSubscriptions = $user->subscriptions('stripe');
$chipSubscriptions = $user->subscriptions('chip');

// Get all subscriptions
$allSubscriptions = $user->subscriptions();

// Check subscription on specific gateway
if ($user->subscribedOn('streaming', 'stripe')) {
    // Has streaming subscription on Stripe
}
```

### Database Schema

Subscriptions are stored with a `gateway` column to track which gateway manages them:

```php
Schema::create('gateway_subscriptions', function (Blueprint $table) {
    $table->id();
    $table->morphs('billable');
    $table->string('gateway')->default('stripe')->index();
    $table->string('gateway_id')->index();
    $table->string('gateway_status')->nullable();
    $table->string('gateway_price')->nullable();
    $table->string('type')->index();
    $table->integer('quantity')->nullable();
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('ends_at')->nullable();
    $table->timestamps();
});
```

## Events

All events extend a base class and include gateway information:

### Subscription Events

- `SubscriptionCreated` - New subscription created
- `SubscriptionUpdated` - Subscription modified
- `SubscriptionCanceled` - Subscription canceled
- `SubscriptionResumed` - Subscription resumed
- `SubscriptionRenewed` - Subscription renewed
- `SubscriptionTrialEnding` - Trial ending soon

### Payment Events

- `PaymentSucceeded` - Payment completed
- `PaymentFailed` - Payment failed
- `PaymentRefunded` - Payment refunded

### Listening to Events

```php
use AIArmada\Cashier\Events\SubscriptionCreated;

class SubscriptionEventListener
{
    public function handle(SubscriptionCreated $event)
    {
        $subscription = $event->subscription();
        $gateway = $event->gateway();
        $billable = $event->billable();
        
        // Handle the event
    }
}
```

## Webhooks

Each gateway has its own webhook endpoint. Configure webhook secrets in your environment:

```env
STRIPE_WEBHOOK_SECRET=whsec_xxx
CHIP_WEBHOOK_KEY=your_webhook_key
```

### Webhook Routes

```
POST /cashier/webhook/stripe
POST /cashier/webhook/chip
```

## Testing

### Using the Test Case

```php
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

it('can create a subscription', function () {
    $user = $this->createUser();
    $subscription = $this->createSubscription($user);
    
    expect($subscription->valid())->toBeTrue();
});
```

### Faking Gateways

```php
use AIArmada\Cashier\Cashier;

// In your test
Cashier::fake();

// Make assertions about gateway calls
Cashier::assertCharged(1000);
Cashier::assertSubscriptionCreated('default');
```

## API Reference

### Contracts

- `GatewayContract` - Gateway interface
- `BillableContract` - Billable model interface
- `CustomerContract` - Customer wrapper interface
- `SubscriptionContract` - Subscription wrapper interface
- `SubscriptionBuilderContract` - Subscription builder interface
- `SubscriptionItemContract` - Subscription item interface
- `PaymentContract` - Payment wrapper interface
- `PaymentMethodContract` - Payment method interface
- `InvoiceContract` - Invoice wrapper interface
- `InvoiceLineItemContract` - Invoice line item interface
- `CheckoutContract` - Checkout session interface
- `CheckoutBuilderContract` - Checkout builder interface

### Exceptions

- `CashierException` - Base exception
- `GatewayNotFoundException` - Gateway not found
- `InvalidGatewayException` - Invalid gateway configuration
- `PaymentFailedException` - Payment processing failed
- `PaymentActionRequired` - Additional action needed (3DS, etc.)
- `CustomerNotFoundException` - Customer not found
- `SubscriptionNotFoundException` - Subscription not found
- `SubscriptionUpdateFailure` - Subscription update failed
- `WebhookVerificationException` - Webhook signature invalid

### Extending with Custom Gateways

```php
use AIArmada\Cashier\Cashier;
use AIArmada\Cashier\Gateways\AbstractGateway;

class PayPalGateway extends AbstractGateway
{
    public function name(): string
    {
        return 'paypal';
    }
    
    // Implement other methods...
}

// Register the gateway
Cashier::manager()->extend('paypal', function ($app) {
    $config = config('cashier.gateways.paypal');
    return new PayPalGateway($config);
});

// Use it
$user->gateway('paypal')->charge($amount);
```

## License

MIT License. See [LICENSE](LICENSE) for details.
