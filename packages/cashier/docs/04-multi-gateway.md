# Multi-Gateway Usage

This guide covers advanced multi-gateway scenarios where users can have subscriptions and payments across different payment providers.

## Overview

AIArmada Cashier allows you to:

- Have subscriptions on multiple gateways simultaneously
- Process payments through different gateways
- Maintain separate customer IDs per gateway
- Query subscriptions by gateway

## Gateway Comparison

| Feature | Stripe | CHIP |
|---------|--------|------|
| **Native Subscriptions** | ✅ Yes | ❌ No (local) |
| **Automatic Renewals** | ✅ Automatic | ❌ Scheduled |
| **Recurring Tokens** | ✅ Payment Methods | ✅ Recurring Tokens |
| **Checkout Sessions** | ✅ Yes | ✅ Purchases |
| **Webhooks** | ✅ Yes | ✅ Yes |
| **Scheduler Required** | No | **Yes** |

> **CHIP Users:** Since CHIP doesn't have native subscriptions, you must schedule renewals:
> ```php
> // In app/Console/Kernel.php
> $schedule->command('cashier-chip:renew-subscriptions')->hourly();
> ```

## Why Multi-Gateway?

Common scenarios:

1. **Regional Payment Methods**: Use Stripe for international cards, CHIP for Malaysian FPX
2. **Product Segmentation**: Different products on different gateways
3. **Migration**: Gradually migrate from one gateway to another
4. **Redundancy**: Fallback to another gateway if one is down

## Customer IDs

Each gateway maintains its own customer ID:

### Storage

```php
// Users table has columns for each gateway
Schema::table('users', function (Blueprint $table) {
    $table->string('stripe_id')->nullable()->index();
    $table->string('chip_id')->nullable()->index();
    // Add more as needed
});
```

### Creating Customers

```php
// Create on default gateway
$user->createAsCustomer([
    'email' => $user->email,
    'name' => $user->name,
]);

// Create on specific gateway
$user->gateway('chip')->createCustomer($user, [
    'email' => $user->email,
    'name' => $user->name,
    'phone' => $user->phone,
]);
```

### Retrieving Customer IDs

```php
// Get ID for specific gateway
$stripeId = $user->gatewayId('stripe'); // 'cus_xxx'
$chipId = $user->gatewayId('chip');     // 'cus_yyy'

// Get all gateway IDs
$ids = $user->gatewayIds();
// ['stripe' => 'cus_xxx', 'chip' => 'cus_yyy']
```

## Multi-Gateway Subscriptions

### Creating Subscriptions on Different Gateways

```php
// Stripe subscription for international streaming service
$streamingSubscription = $user->newSubscription('streaming', 'price_streaming', 'stripe')
    ->trialDays(7)
    ->create($stripePaymentMethod);

// CHIP subscription for local gym membership
$gymSubscription = $user->newSubscription('gym', 'gym_monthly', 'chip')
    ->create();

// Another Stripe subscription
$softwareSubscription = $user->newSubscription('software', 'price_software', 'stripe')
    ->create($stripePaymentMethod);
```

### Querying Subscriptions

```php
// Get all subscriptions
$allSubscriptions = $user->subscriptions();

// Get subscriptions for specific gateway
$stripeSubscriptions = $user->subscriptions('stripe');
$chipSubscriptions = $user->subscriptions('chip');

// Get specific subscription by type
$streaming = $user->subscription('streaming');

// Using Eloquent scopes
use AIArmada\Cashier\Models\Subscription;

$activeStripe = Subscription::active()->forGateway('stripe')->get();
$canceledChip = Subscription::canceled()->forGateway('chip')->get();
```

### Checking Subscription Status

```php
// Check if subscribed to any plan on any gateway
if ($user->subscribed()) {
    // Has at least one active subscription
}

// Check specific subscription type
if ($user->subscribed('streaming')) {
    // Has active streaming subscription (on any gateway)
}

// Check subscription on specific gateway
$subscription = $user->subscription('gym');
if ($subscription && $subscription->gateway() === 'chip') {
    // Gym subscription is on CHIP
}
```

## Multi-Gateway Payments

### Charging on Different Gateways

```php
// Charge on default gateway (Stripe)
$payment = $user->charge(1000, $stripePaymentMethod);

// Charge on CHIP
$payment = $user->charge(5000, $chipPaymentMethod, [
    'gateway' => 'chip',
]);
```

### Gateway-Specific Payment Methods

```php
// Stripe payment methods
$stripePaymentMethods = $user->gateway('stripe')->paymentMethods();

// CHIP payment methods (FPX, etc.)
$chipPaymentMethods = $user->gateway('chip')->paymentMethods();
```

### Checkout on Different Gateways

```php
// Stripe checkout for international products
$stripeCheckout = $user->checkout([
    ['price' => 'price_international', 'quantity' => 1],
], 'stripe')
    ->successUrl(route('checkout.success'))
    ->cancelUrl(route('checkout.cancel'))
    ->create();

// CHIP checkout for Malaysian customers
$chipCheckout = $user->checkout([
    ['price' => 'price_local', 'quantity' => 1],
], 'chip')
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

// Usage
$gateway = $this->selectGateway($user, $order->currency);
$payment = $user->charge($amount, $paymentMethod, ['gateway' => $gateway]);
```

### Based on User Location

```php
public function selectGateway(User $user): string
{
    return match ($user->country) {
        'MY' => 'chip',     // Malaysia
        'SG' => 'stripe',   // Singapore
        'ID' => 'chip',     // Indonesia (if CHIP supports)
        default => 'stripe',
    };
}
```

### Based on Product Type

```php
// In config/cashier.php
'subscription_gateways' => [
    'streaming' => 'stripe',  // International streaming
    'gym' => 'chip',          // Local gym
    'software' => 'stripe',   // SaaS product
],

// Usage
$gateway = config("cashier.subscription_gateways.{$type}", config('cashier.default'));
$subscription = $user->newSubscription($type, $price, $gateway)->create();
```

## Data Model

### Subscription Table

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

    // Unique: one subscription type per gateway per user
    $table->unique(['billable_type', 'billable_id', 'type', 'gateway']);
});
```

## Event Handling

All events include gateway information:

```php
use AIArmada\Cashier\Events\SubscriptionCreated;

class HandleSubscriptionCreated
{
    public function handle(SubscriptionCreated $event)
    {
        $subscription = $event->subscription();
        $gateway = $event->gateway(); // 'stripe' or 'chip'
        $user = $event->billable();
        
        // Gateway-specific handling
        match ($gateway) {
            'stripe' => $this->handleStripeSubscription($subscription),
            'chip' => $this->handleChipSubscription($subscription),
        };
    }
}
```

## Best Practices

### 1. Always Specify Gateway Explicitly

```php
// Explicit is better than implicit
$subscription = $user->newSubscription('plan', 'price_id', 'stripe')->create();

// Instead of relying on default
$subscription = $user->newSubscription('plan', 'price_id')->create();
```

### 2. Track Gateway in Your Orders

```php
// Store which gateway was used
$order->update([
    'payment_gateway' => $gateway,
    'payment_id' => $payment->id(),
]);
```

### 3. Handle Gateway-Specific Errors

```php
try {
    $payment = $user->charge($amount, $pm, ['gateway' => $gateway]);
} catch (PaymentFailedException $e) {
    if ($e->gateway() === 'chip') {
        // CHIP-specific error handling
    } else {
        // Stripe-specific error handling
    }
}
```

### 4. Unified Webhook Handling

```php
// Routes
Route::post('webhook/stripe', [StripeWebhookController::class, 'handle']);
Route::post('webhook/chip', [ChipWebhookController::class, 'handle']);

// Both controllers dispatch unified events
event(new PaymentSucceeded($payment, $gateway, $billable));
```

### 5. Migration Strategy

When migrating between gateways:

```php
// Create new subscription on new gateway
$newSubscription = $user->newSubscription('plan', 'new_price', 'chip')
    ->skipTrial() // User already had trial on Stripe
    ->create();

// Cancel old subscription at period end
$oldSubscription = $user->subscription('plan', 'stripe');
$oldSubscription->cancel();

// Once old subscription ends, new one takes over
```
