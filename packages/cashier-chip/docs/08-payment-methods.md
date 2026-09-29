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

Recurring tokens are encrypted at rest (`encrypted` cast). Token lookups compare decrypted
values in the application layer, and per-billable uniqueness is enforced there as well. Only
an explicitly flagged method is returned as default; when nothing is marked default,
`defaultPaymentMethod()` returns `null`. Stored metadata keeps only the purchase/token id,
payment method, and description — never the full CHIP payload.

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
    'idempotency_key' => 'setup-attempt-123',
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
    'idempotency_key' => 'setup-attempt-123',
]);

return redirect($url);
```

### After Webhook

Recurring tokens are saved when the `purchase.paid` or `purchase.preauthorized` webhook arrives with
a `recurring_token` on the purchase (see [Webhooks](10-webhooks.md)).

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

There is no public `addPaymentMethod()` helper on the billable API, and there are no dedicated
payment-method events. Recurring tokens are stored as a side effect of:

- a `purchase.paid` or `purchase.preauthorized` webhook carrying a `recurring_token`
- the package syncing tokens back from CHIP for an existing linked customer

## Payment Method Properties

Each payment method record exposes:

| Property | Description |
|----------|-------------|
| `id()` | The recurring token string, or `null` when none is stored |
| `type()` | Payment-method type from CHIP (`card`, `fpx`, …) |
| `brand()` | Card or payment-method brand from the local record |
| `lastFour()` | Last 4 digits when the masked PAN is available |
| `expirationMonth()` | Always `null` — CHIP does not return card expiry |
| `expirationYear()` | Always `null` — CHIP does not return card expiry |
| `isDefault()` | Whether this is the current default method |
| `delete()` | Removes the local record and revokes the CHIP token |

For Blade or presentation helpers, the wrapper also exposes aliases such as `cardBrand()`, `cardLastFour()`, `cardExpMonth()`, and `cardExpYear()`. `cardExpMonth()`/`cardExpYear()` are aliases of the `null`-returning expiry accessors.

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

## Database Schema

Payment methods are stored in `cashier_chip_payment_methods` (rename via
`cashier-chip.database.table_prefix`):

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `owner_type` | string nullable | Owner scope morph type when multitenancy is enabled |
| `owner_id` | uuid nullable | Owner scope morph key when multitenancy is enabled |
| `billable_id` | uuid | Foreign key to billable |
| `billable_type` | string | Billable model class |
| `owner_id` | uuid nullable | Owner scope morph key when multitenancy is enabled |
| `owner_type` | string nullable | Owner scope morph type when multitenancy is enabled |
| `recurring_token` | text | CHIP recurring token (encrypted at rest) |
| `type` | string nullable | Payment-method type |
| `brand` | string nullable | Card or payment-method brand |
| `last_four` | string(4) nullable | Last 4 digits |
| `is_default` | boolean | Default flag |
| `metadata` | json nullable | Minimal token identifiers (purchase/token id, method, description) |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
