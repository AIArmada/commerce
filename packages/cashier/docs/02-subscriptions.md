# Subscriptions

This guide covers everything you need to know about managing subscriptions with AIArmada Cashier.

## Gateway Differences

> **Important:** Subscription handling differs between gateways.

| Feature | Stripe | CHIP |
|---------|--------|------|
| **Native Subscriptions** | ✅ Yes | ❌ No |
| **Automatic Renewals** | ✅ Stripe handles it | ❌ Your app handles it |
| **Scheduler Required** | No | **Yes** |

### CHIP Subscription Renewals

Since CHIP doesn't have native subscriptions, your application must schedule renewals. Add this to your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // Process CHIP subscription renewals every hour
    $schedule->command('cashier-chip:renew-subscriptions')
        ->hourly()
        ->withoutOverlapping();
}
```

The command:
- Finds subscriptions where `next_billing_at <= now()`
- Charges the stored `recurring_token`
- Updates `next_billing_at` to the next billing period
- Marks subscriptions as `past_due` if payment fails
- Dispatches `SubscriptionRenewed` or `SubscriptionRenewalFailed` events

You can also run it manually with options:

```bash
# Dry run - see what would be renewed
php artisan cashier-chip:renew-subscriptions --dry-run

# With grace period (hours before considering due)
php artisan cashier-chip:renew-subscriptions --grace-hours=2
```

## Creating Subscriptions

### Basic Subscription

```php
$subscription = $user->newSubscription('default', 'price_monthly')
    ->create($paymentMethod);
```

### With Trial Period

```php
// Trial for 14 days
$subscription = $user->newSubscription('default', 'price_monthly')
    ->trialDays(14)
    ->create($paymentMethod);

// Trial until specific date
$subscription = $user->newSubscription('default', 'price_monthly')
    ->trialUntil(now()->addMonth())
    ->create($paymentMethod);
```

### Without Trial (Skip Trial)

```php
$subscription = $user->newSubscription('default', 'price_monthly')
    ->skipTrial()
    ->create($paymentMethod);
```

### With Quantity

```php
// For metered or per-seat billing
$subscription = $user->newSubscription('default', 'price_per_seat')
    ->quantity(5) // 5 seats
    ->create($paymentMethod);
```

### On Specific Gateway

```php
// Create on CHIP instead of default Stripe
$subscription = $user->newSubscription('local', 'price_id', 'chip')
    ->create();
```

## Checking Subscription Status

### Basic Status Checks

```php
// Check if user has any active subscription
if ($user->subscribed()) {
    // User is subscribed
}

// Check specific subscription type
if ($user->subscribed('premium')) {
    // User has premium subscription
}

// Check if on trial (model level or subscription level)
if ($user->onTrial()) {
    // User is on trial
}
```

### Subscription Object Status

```php
$subscription = $user->subscription('default');

// Is the subscription valid (active, trial, or grace period)?
$subscription->valid();

// Is the subscription currently active?
$subscription->active();

// Is the subscription on trial?
$subscription->onTrial();

// Has trial expired?
$subscription->hasExpiredTrial();

// Is the subscription canceled?
$subscription->canceled();

// Is subscription in grace period after cancellation?
$subscription->onGracePeriod();

// Has the subscription fully ended?
$subscription->ended();

// Is the subscription recurring (not on trial and not canceled)?
$subscription->recurring();

// Has incomplete payment?
$subscription->incomplete();
$subscription->pastDue();
$subscription->hasIncompletePayment();
```

## Managing Subscriptions

### Retrieving Subscriptions

```php
// Get a specific subscription by type
$subscription = $user->subscription('default');

// Get all subscriptions
$subscriptions = $user->subscriptions();

// Get subscriptions for specific gateway
$stripeSubscriptions = $user->subscriptions('stripe');
$chipSubscriptions = $user->subscriptions('chip');
```

### Updating Quantity

```php
$subscription = $user->subscription('default');

// Update to specific quantity
$subscription->updateQuantity(10);

// Increment quantity
$subscription->incrementQuantity();     // +1
$subscription->incrementQuantity(5);    // +5

// Decrement quantity
$subscription->decrementQuantity();     // -1
$subscription->decrementQuantity(3);    // -3
```

### Swapping Plans

```php
$subscription = $user->subscription('default');

// Swap to a different price
$subscription->swap('price_yearly');

// For multi-price subscriptions, swap specific item
$subscription->findItemOrFail('price_old')->swap('price_new');
```

## Canceling Subscriptions

### Cancel at Period End

The subscription remains active until the end of the current billing period:

```php
$subscription = $user->subscription('default');
$subscription->cancel();

// Check if canceled but still accessible
if ($subscription->onGracePeriod()) {
    // User can still use the service
}
```

### Cancel Immediately

```php
$subscription->cancelNow();

// The subscription is immediately ended
if ($subscription->ended()) {
    // Subscription is no longer active
}
```

### Cancel at Specific Date

```php
$subscription->cancelAt(now()->addDays(30));
```

## Resuming Subscriptions

A canceled subscription can be resumed while on grace period:

```php
$subscription = $user->subscription('default');

if ($subscription->onGracePeriod()) {
    $subscription->resume();
    
    // Subscription is now active again
    if ($subscription->active()) {
        echo "Subscription resumed!";
    }
}
```

**Note:** You cannot resume a subscription that has fully ended.

## Trial Management

### Extending Trial

```php
$subscription = $user->subscription('default');

// Extend trial to a future date
$subscription->extendTrial(now()->addDays(30));
```

### Ending Trial Early

```php
$subscription = $user->subscription('default');

// End trial immediately
$subscription->endTrial();
```

### Skip Trial on New Subscription

```php
$subscription = $user->newSubscription('default', 'price_monthly')
    ->skipTrial()
    ->create($paymentMethod);
```

## Multi-Price Subscriptions

For subscriptions with multiple line items:

```php
// Check if subscription has multiple prices
if ($subscription->hasMultiplePrices()) {
    // Handle multi-price subscription
}

// Check for specific price
if ($subscription->hasPrice('price_addon')) {
    // User has the addon
}

// Get specific item
$item = $subscription->findItemOrFail('price_addon');

// Update item quantity
$item->updateQuantity(3);
$item->incrementQuantity();
$item->decrementQuantity();

// Swap item to different price
$item->swap('price_new_addon');
```

## Subscription Scopes

Use Eloquent scopes to query subscriptions:

```php
use AIArmada\Cashier\Models\Subscription;

// Active subscriptions
$active = Subscription::active()->get();

// Canceled subscriptions
$canceled = Subscription::canceled()->get();

// On trial subscriptions
$onTrial = Subscription::onTrial()->get();

// On grace period
$gracePeriod = Subscription::onGracePeriod()->get();

// Incomplete subscriptions
$incomplete = Subscription::incomplete()->get();

// Past due subscriptions
$pastDue = Subscription::pastDue()->get();

// Filter by gateway
$stripeOnly = Subscription::forGateway('stripe')->get();
$chipOnly = Subscription::forGateway('chip')->get();

// Combine scopes
$activeStripe = Subscription::active()->forGateway('stripe')->get();
```

## Subscription Events

Listen to subscription lifecycle events:

```php
use AIArmada\Cashier\Events\SubscriptionCreated;
use AIArmada\Cashier\Events\SubscriptionCanceled;
use AIArmada\Cashier\Events\SubscriptionResumed;

// In EventServiceProvider
protected $listen = [
    SubscriptionCreated::class => [
        SendWelcomeEmail::class,
    ],
    SubscriptionCanceled::class => [
        SendCancellationSurvey::class,
    ],
];
```

## Error Handling

```php
use AIArmada\Cashier\Exceptions\SubscriptionUpdateFailure;
use AIArmada\Cashier\Exceptions\SubscriptionNotFoundException;

try {
    $subscription = $user->subscription('premium');
    
    if (!$subscription) {
        throw SubscriptionNotFoundException::create('premium');
    }
    
    $subscription->swap('new_price');
} catch (SubscriptionUpdateFailure $e) {
    // Handle update failure
    logger()->error('Subscription update failed: ' . $e->getMessage());
} catch (SubscriptionNotFoundException $e) {
    // Handle not found
    return redirect()->route('subscribe');
}
```

## Best Practices

1. **Always check status before operations:**
   ```php
   if ($subscription->valid()) {
       // Safe to perform operations
   }
   ```

2. **Handle incomplete payments:**
   ```php
   if ($subscription->hasIncompletePayment()) {
       // Prompt user to complete payment
   }
   ```

3. **Use scopes for queries:**
   ```php
   // Instead of manual filtering
   $active = Subscription::active()->forGateway('stripe')->get();
   ```

4. **Listen to events for side effects:**
   ```php
   // Don't put business logic in controllers
   // Use event listeners instead
   ```
