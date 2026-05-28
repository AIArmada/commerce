---
title: Payment Methods
---

# Payment Methods

CHIP uses **recurring tokens** as payment methods—equivalent to Stripe's PaymentMethods.

## Understanding Recurring Tokens

When a customer completes a checkout with `force_recurring = true`, CHIP returns a **recurring token**. This token can be used for:

- Subscription renewals
- One-click payments
- Automatic charges

## Retrieving Payment Methods

### All Payment Methods

```php
// Get all saved payment methods
$paymentMethods = $user->paymentMethods();

foreach ($paymentMethods as $method) {
    echo $method->id();
    echo $method->brand();
    echo $method->lastFour();
}
```

### Default Payment Method

```php
// Get the default payment method
$default = $user->defaultPaymentMethod();

// Check if user has a default payment method
if ($user->hasDefaultPaymentMethod()) {
    // Can charge immediately
}
```

## Adding Payment Methods

### Via Setup Purchase

The recommended way to add payment methods:

```php
// Create zero-amount purchase to save card
$checkout = $user->createSetupPurchase([
    'success_url' => route('billing.methods'),
    'cancel_url' => route('billing.methods'),
]);

return redirect($checkout->checkout_url);
```

### Via Regular Checkout

Request a recurring token during any checkout:

```php
$checkout = $user->checkout(10000, [
    'recurring' => true,
]);
```

### Convenience URL

```php
// Get a URL to redirect for adding payment method
$url = $user->setupPaymentMethodUrl([
    'success_url' => route('billing.methods'),
    'cancel_url' => route('billing.methods'),
]);

return redirect($url);
```

### After Webhook

Recurring tokens are automatically saved when webhooks are received for successful payments with `force_recurring = true`.

## Managing Payment Methods

### Set Default Payment Method

```php
// Update the default payment method
$user->updateDefaultPaymentMethod($recurringToken);
```

### Delete Payment Method

```php
// Delete a specific payment method
$user->deletePaymentMethod($recurringToken);

// Note: CHIP may not support revoking recurring tokens via API
// This removes the local record only
```

There is no public `addPaymentMethod()` helper on the billable API. Recurring tokens are stored automatically when:

- a successful webhook includes `force_recurring = true`
- the package syncs tokens back from CHIP for an existing linked customer

## Payment Method Properties

Each payment method record contains:

| Property | Description |
|----------|-------------|
| `id()` | The recurring token string |
| `brand()` | Card or payment-method brand |
| `lastFour()` | Last 4 digits when provided |
| `expirationMonth()` | Expiry month when CHIP returns it |
| `expirationYear()` | Expiry year when CHIP returns it |
| `isDefault()` | Whether this is the current default method |

For Blade or presentation helpers, the wrapper also exposes aliases such as `cardBrand()`, `cardLastFour()`, `cardExpMonth()`, and `cardExpYear()`.

## Charging with Payment Methods

### Using Default Method

```php
// Charge using default payment method
$payment = $user->charge(10000);
```

### Using Specific Method

```php
// Charge using a specific recurring token
$payment = $user->chargeWithRecurringToken(
    amount: 10000,
    recurringToken: $recurringToken,
    options: [
        'reference' => 'Order #123',
    ]
);
```

## Checking Payment Method Availability

```php
// Check if can charge immediately (has valid payment method)
if ($user->hasDefaultPaymentMethod()) {
    $payment = $user->charge(10000);
} else {
    // Redirect to add payment method
    return redirect()->route('billing.add-method');
}
```

## Payment Method Events

Listen for payment method changes:

```php
use AIArmada\CashierChip\Events\PaymentMethodAdded;
use AIArmada\CashierChip\Events\PaymentMethodRemoved;
use AIArmada\CashierChip\Events\DefaultPaymentMethodChanged;

// In EventServiceProvider
protected $listen = [
    PaymentMethodAdded::class => [
        SendPaymentMethodAddedNotification::class,
    ],
];
```

## Database Schema

Payment methods are stored in `cashier_chip_payment_methods`:

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `billable_id` | uuid | Foreign key to billable |
| `billable_type` | string | Billable model class |
| `recurring_token` | string | CHIP recurring token |
| `type` | string nullable | Payment-method type |
| `brand` | string nullable | Card or payment-method brand |
| `last_four` | string | Last 4 digits |
| `is_default` | boolean | Default flag |
| `metadata` | json nullable | Raw token payload and synced details |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
