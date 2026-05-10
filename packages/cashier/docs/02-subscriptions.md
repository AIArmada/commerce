---
title: Subscriptions
---

# Subscriptions

This guide covers the unified subscription helpers exposed by `aiarmada/cashier`.

## Gateway Differences

> **Important:** Subscription behavior still depends on the underlying gateway package.

| Feature | Stripe | CHIP |
|---------|--------|------|
| **Native subscriptions** | ✅ Yes | ❌ No |
| **Automatic renewals** | ✅ Handled by Stripe | ❌ Your app must renew them |
| **Scheduler required** | No | **Yes** |

### CHIP Subscription Renewals

Since CHIP does not provide native subscription renewal, your application must schedule it:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('cashier-chip:renew-subscriptions')
        ->hourly()
        ->withoutOverlapping();
}
```

You can also run it manually:

```bash
php artisan cashier-chip:renew-subscriptions --dry-run
php artisan cashier-chip:renew-subscriptions --grace-hours=2
```

## Creating Subscriptions

### Basic Subscription

```php
$subscription = $user->newGatewaySubscription('default', 'price_monthly')
    ->create($paymentMethod);
```

### With Trial Period

```php
$subscription = $user->newGatewaySubscription('default', 'price_monthly')
    ->trialDays(14)
    ->create($paymentMethod);

$subscription = $user->newGatewaySubscription('default', 'price_monthly')
    ->trialUntil(now()->addMonth())
    ->create($paymentMethod);
```

### Without Trial

```php
$subscription = $user->newGatewaySubscription('default', 'price_monthly')
    ->skipTrial()
    ->create($paymentMethod);
```

### With Quantity

```php
$subscription = $user->newGatewaySubscription('default', 'price_per_seat')
    ->quantity(5)
    ->create($paymentMethod);
```

### On a Specific Gateway

```php
$stripeSubscription = $user->newGatewaySubscription('default', 'price_monthly', 'stripe')
    ->create($stripePaymentMethod);

$chipSubscription = $user->newGatewaySubscription('local-plan', 'gym_monthly', 'chip')
    ->create($chipRecurringToken);
```

### With Multiple Prices

```php
$subscription = $user->newGatewaySubscription('bundle', [
    'price_base',
    'price_addon',
], 'stripe')->create($stripePaymentMethod);
```

## Checking Subscription Status

### Cross-Gateway Status Checks

```php
$hasAnyValidSubscription = $user->allSubscriptions()
    ->contains(fn ($subscription) => $subscription->valid());

if ($user->subscribedOnAny('premium')) {
    // User has an active premium subscription on any installed gateway.
}

if ($user->subscribedViaGateway(type: 'premium', gateway: 'stripe')) {
    // User has an active premium subscription specifically on Stripe.
}

if ($user->onTrialOnAny('default')) {
    // At least one matching subscription is currently on trial.
}
```

### Subscription Object Status

```php
$subscription = $user->findSubscription('default');

if (! $subscription) {
    return;
}

$subscription->valid();
$subscription->active();
$subscription->onTrial();
$subscription->hasExpiredTrial();
$subscription->canceled();
$subscription->onGracePeriod();
$subscription->ended();
$subscription->recurring();
$subscription->incomplete();
$subscription->pastDue();
$subscription->hasIncompletePayment();
```

## Retrieving Subscriptions

```php
// First matching subscription across installed gateways.
$subscription = $user->findSubscription('default');

// Every subscription across installed gateways.
$allSubscriptions = $user->allSubscriptions();

// Subscriptions for one gateway only.
$stripeSubscriptions = $user->gatewaySubscriptions('stripe');
$chipSubscriptions = $user->gatewaySubscriptions('chip');

// One subscription from one gateway.
$chipGym = $user->gatewaySubscription('gym', 'chip');
```

## Updating Subscriptions

```php
$subscription = $user->findSubscription('default');

if (! $subscription) {
    return;
}

$subscription->updateQuantity(10);
$subscription->incrementQuantity();
$subscription->incrementQuantity(5);
$subscription->decrementQuantity();
$subscription->decrementQuantity(3);

$subscription->swap('price_yearly');
```

## Canceling and Resuming Subscriptions

### Cancel at Period End

```php
$subscription = $user->findSubscription('default');

if ($subscription) {
    $subscription->cancel();
}
```

### Cancel Immediately

```php
$subscription = $user->findSubscription('default');

if ($subscription) {
    $subscription->cancelNow();
}
```

### Resume During Grace Period

```php
$subscription = $user->findSubscription('default');

if ($subscription && $subscription->onGracePeriod()) {
    $subscription->resume();
}
```

## Working with Subscription Items

```php
$subscription = $user->findSubscription('default');

if (! $subscription) {
    return;
}

if ($subscription->hasPrice('price_addon')) {
    $item = $subscription->items()
        ->first(fn ($item) => $item->priceId() === 'price_addon');

    $item?->updateQuantity(3);
    $item?->incrementQuantity();
    $item?->decrementQuantity();
    $item?->swap('price_new_addon');
}
```

## When You Need Gateway-Specific Features

`aiarmada/cashier` intentionally exposes a smaller, unified subscription surface.

For gateway-only features such as advanced proration flags, dashboard-specific metadata,
or direct access to the underlying Stripe / CHIP models, drop down to the wrapped object:

```php
$gatewaySubscription = $subscription->asGatewaySubscription();
```

Use that sparingly and keep the unified helpers as your default API.
