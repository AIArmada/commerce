---
title: Multi-Gateway Usage
---

# Multi-Gateway Usage

This guide covers scenarios where the same billable model uses more than one gateway at the same time.

## Overview

AIArmada Cashier lets you:

- create subscriptions on different gateways for the same user
- charge through different gateways per order or region
- keep separate customer IDs per gateway
- query subscriptions, invoices, and payment methods without hard-coding one provider

## Gateway Comparison

| Feature | Stripe | CHIP |
|---------|--------|------|
| **Native subscriptions** | ✅ Yes | ❌ No (local renewal flow) |
| **Automatic renewals** | ✅ Automatic | ❌ Scheduled |
| **Hosted checkout** | ✅ Yes | ✅ Yes |
| **Webhooks** | ✅ Yes | ✅ Yes |
| **Scheduler required** | No | **Yes** |

> **CHIP users:** schedule `cashier-chip:renew-subscriptions` in your console kernel.

## Customer IDs

Each installed gateway keeps its own customer identifier on the billable model.

### Storage

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('stripe_id')->nullable()->index();
    $table->string('chip_id')->nullable()->index();
});
```

Those columns come from the gateway packages:

- `laravel/cashier` owns `stripe_id`
- `aiarmada/cashier-chip` owns `chip_id`

### Creating / Syncing Customers

```php
$user->createOrGetCustomer(options: [
    'email' => $user->email,
    'name' => $user->name,
]);

$user->createOrGetCustomer(gateway: 'chip', options: [
    'email' => $user->email,
    'name' => $user->name,
    'phone' => $user->phone,
]);

$user->syncCustomer(gateway: 'stripe', options: [
    'email' => $user->email,
    'name' => $user->name,
]);
```

### Retrieving Gateway IDs

```php
$stripeId = $user->gatewayId('stripe');
$chipId = $user->gatewayId('chip');

$ids = collect([
    'stripe' => $stripeId,
    'chip' => $chipId,
])->filter()->all();
```

## Multi-Gateway Subscriptions

### Creating Subscriptions on Different Gateways

```php
$streamingSubscription = $user->newGatewaySubscription('streaming', 'price_streaming', 'stripe')
    ->trialDays(7)
    ->create($stripePaymentMethod);

$gymSubscription = $user->newGatewaySubscription('gym', 'gym_monthly', 'chip')
    ->create($chipRecurringToken);

$softwareSubscription = $user->newGatewaySubscription('software', 'price_software', 'stripe')
    ->create($stripePaymentMethod);
```

### Querying Subscriptions

```php
$allSubscriptions = $user->allSubscriptions();

$stripeSubscriptions = $user->gatewaySubscriptions('stripe');
$chipSubscriptions = $user->gatewaySubscriptions('chip');

$streaming = $user->findSubscription('streaming');
$gymOnChip = $user->gatewaySubscription('gym', 'chip');

$activeStripe = $user->gatewaySubscriptions('stripe')
    ->filter(fn ($subscription) => $subscription->active());

$canceledChip = $user->gatewaySubscriptions('chip')
    ->filter(fn ($subscription) => $subscription->canceled());
```

### Checking Status

```php
if ($user->subscribedOnAny('streaming')) {
    // Active streaming subscription exists on at least one gateway.
}

if ($user->subscribedViaGateway(type: 'gym', gateway: 'chip')) {
    // Gym subscription is active on CHIP.
}
```

## Multi-Gateway Payments

### Charging on Different Gateways

```php
$stripePayment = $user->chargeWithGateway(1000, $stripePaymentMethod, 'stripe');

$chipPayment = $user->chargeWithGateway(5000, $chipPaymentMethod, 'chip');
```

### Payment Methods

```php
$stripePaymentMethods = $user->gatewayPaymentMethods('stripe');
$chipPaymentMethods = $user->gatewayPaymentMethods('chip');

$defaultStripeMethod = $user->defaultGatewayPaymentMethod('stripe');
```

### Checkout on Different Gateways

```php
$stripeCheckout = $user->checkoutWithGateway('stripe')
    ->price('price_international')
    ->successUrl(route('checkout.success'))
    ->cancelUrl(route('checkout.cancel'))
    ->create();

$chipCheckout = $user->checkoutWithGateway('chip')
    ->price('price_local')
    ->successUrl(route('checkout.success'))
    ->cancelUrl(route('checkout.cancel'))
    ->create();
```

## Gateway Selection Strategies

### Based on Currency

```php
public function selectGateway(User $user, string $currency): string
{
    return match ($currency) {
        'MYR' => 'chip',
        'SGD' => 'stripe',
        default => config('cashier.default'),
    };
}

$gateway = $this->selectGateway($user, $order->currency);
$payment = $user->chargeWithGateway($amount, $paymentMethod, $gateway);
```

### Based on User Location

```php
public function selectGateway(User $user): string
{
    return match ($user->country) {
        'MY' => 'chip',
        'SG' => 'stripe',
        default => 'stripe',
    };
}
```

### Based on Product Type

```php
'subscription_gateways' => [
    'streaming' => 'stripe',
    'gym' => 'chip',
    'software' => 'stripe',
],

$gateway = config("cashier.subscription_gateways.{$type}", config('cashier.default'));

$subscription = $user->newGatewaySubscription($type, $price, $gateway)
    ->create($paymentMethod);
```

## Data Model

### Gateway-Owned Tables

```php
// Stripe schema comes from laravel/cashier
subscriptions
subscription_items
users.stripe_id

// CHIP schema comes from aiarmada/cashier-chip
chip_subscriptions
chip_subscription_items
users.chip_id
```

`aiarmada/cashier` does not create a unified `gateway_subscriptions` table. It wraps the
underlying gateway records behind `SubscriptionContract`, `PaymentContract`, `InvoiceContract`,
and the collection helpers shown above.

## Event Handling

All unified events still include gateway context:

```php
use AIArmada\Cashier\Events\SubscriptionCreated;

class HandleSubscriptionCreated
{
    public function handle(SubscriptionCreated $event): void
    {
        $subscription = $event->subscription();
        $gateway = $event->gateway();
        $user = $event->billable();

        match ($gateway) {
            'stripe' => $this->handleStripeSubscription($subscription, $user),
            'chip' => $this->handleChipSubscription($subscription, $user),
        };
    }
}
```

Concrete webhook routes and controllers remain owned by the underlying gateway packages.
`aiarmada/cashier` stays at the unified event layer.

## Best Practices

### 1. Prefer Explicit Gateways

```php
$subscription = $user->newGatewaySubscription('plan', 'price_id', 'stripe')->create($paymentMethod);
```

### 2. Persist Gateway Metadata on Your Own Records

```php
$order->update([
    'payment_gateway' => $gateway,
    'payment_id' => $payment->id(),
]);
```

### 3. Use Unified Reads, Gateway-Native Escapes Only When Needed

```php
$gatewayPayment = $payment->asGatewayPayment();
$gatewaySubscription = $subscription->asGatewaySubscription();
```

### 4. Migrate Between Gateways Explicitly

```php
$newSubscription = $user->newGatewaySubscription('plan', 'new_price', 'chip')
    ->skipTrial()
    ->create($chipRecurringToken);

$oldSubscription = $user->gatewaySubscription('plan', 'stripe');
$oldSubscription?->cancel();
```
