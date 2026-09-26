---
title: One-off Charges
---

# One-off Charges

Process single payments without creating subscriptions.

`ChargeChipCustomer` is the action used by the renewal command, not by `$user->charge()`. A
one-off charge goes straight to the gateway through the `PerformsCharges` trait, which builds
the purchase itself and calls `chargePurchase()` on it. Use `ChargeChipCustomer` when you want
the same customer-linking and payment-method bookkeeping the renewal path performs.

```php
use AIArmada\CashierChip\Actions\ChargeChipCustomer;

$payment = ChargeChipCustomer::run(
    billable: $user,
    amount: 10000,
    recurringToken: null,
    options: ['reference' => 'Product Purchase - Order #123'],
);
```

## Simple Charges

### Charge with Default Payment Method

```php
// Charge 100.00 MYR (amounts are in minor units)
$payment = $user->charge(10000);

// Check payment status
if ($payment->isSucceeded()) {
    // Payment completed
}
```

### Charge with Description

```php
$payment = $user->charge(10000, options: [
    'reference' => 'Product Purchase - Order #123',
]);
```

### Charge with Specific Payment Method

The recurring token is the **second positional argument**, not an option key:

```php
$payment = $user->charge(10000, $recurringToken);
```

## Recurring Token Charges

For saved payment methods (recurring tokens):

```php
// Charge using a specific recurring token
$payment = $user->chargeWithRecurringToken(
    amount: 10000,
    recurringToken: $user->defaultPaymentMethod()?->id(),
    options: [
        'reference' => 'Monthly Service Fee',
    ]
);
```

## Checkout Sessions

For one-off charges that require the customer to enter payment details:

```php
// Create checkout session
$checkout = $user->checkout(10000, [
    'reference' => 'Premium Plan',
]);

// Redirect to CHIP checkout page
return $checkout->redirect();
```

See [Checkout Sessions](07-checkout.md) for detailed checkout documentation.

## Payment Object

All charge methods return a `Payment` object:

```php
$payment = $user->charge(10000);

// Get payment ID
$id = $payment->id();

// Get status
$status = $payment->status();

// Check status methods
$payment->isSucceeded();    // paid | cleared | settled
$payment->isPending();      // awaiting customer action
$payment->isFailed();       // error | blocked
$payment->isExpired();      // status is exactly 'expired'
$payment->isRefunded();     // status is exactly 'refunded'

// Get amount (integer minor units)
$amount = $payment->rawAmount();

// Get the formatted amount (e.g. "RM 100.00")
$display = $payment->amount();

// Get checkout URL (for redirect payments)
$url = $payment->checkoutUrl();

// Get underlying CHIP Purchase object
$purchase = $payment->asChipPurchase();
```

## Payment Statuses

`$payment->status()` returns the raw CHIP purchase status string. The predicates group them like this:

| Predicate | Raw statuses |
|-----------|--------------|
| `isSucceeded()` | `paid`, `cleared`, `settled` |
| `isPending()` | `created`, `sent`, `viewed`, `overdue`, `pending_execute`, `pending_capture`, `pending_charge`, `pending_refund`, `pending_release` |
| `isFailed()` | `error`, `blocked` |
| `isExpired()` | `expired` |
| `isRefunded()` | `refunded` |
| `isCancelled()` | `cancelled`, `released` |
| `requiresCapture()` | `hold` |

## Handling Failures

Charges with a recurring token validate the purchase and throw `IncompletePayment` when it did not
succeed. Charges without a token never throw on status — check the payment instead.

```php
use AIArmada\CashierChip\Exceptions\IncompletePayment;

try {
    $payment = $user->charge(10000, $recurringToken);
} catch (IncompletePayment $e) {
    // Handle payment failure
    $message = $e->getMessage();
}
```

> **info**
> `charge()` and `ChargeChipCustomer` also throw `IncompletePayment` when the per-minute rate limit
> (`cashier-chip.rate_limits.charges_per_minute`) is exhausted.

## Refunds

The canonical way to refund a payment is via the `RefundChipPayment` Action:

```php
use AIArmada\CashierChip\Actions\RefundChipPayment;

// Full refund
RefundChipPayment::run($purchaseId);

// Partial refund (50.00 MYR in minor units)
RefundChipPayment::run($purchaseId, 5000);
```

CHIP refunds can also be processed through the CHIP dashboard or lower-level API:

```php
// Using the CHIP package directly
use AIArmada\Chip\Facades\Chip;

Chip::refundPurchase($purchaseId, 5000); // Partial refund in minor units
```

## Receipts

`send_receipt` is a checkout option, not a charge option — `charge()` ignores it. To have CHIP email
a receipt, create a checkout with `send_receipt` enabled (see [Checkout Sessions](07-checkout.md)).
There is no `sendReceipt()` method on the CHIP facade; receipt sending is a per-purchase flag.

## Currency

All amounts are in the smallest currency unit (minor units for MYR):

| Display Amount | Code Amount |
|----------------|-------------|
| RM 1.00 | 100 |
| RM 10.00 | 1000 |
| RM 100.00 | 10000 |

Format amounts for display:

```php
use AIArmada\CommerceSupport\Support\MoneyFormatter;

$formatted = MoneyFormatter::formatMinor(10000, 'MYR');
```

Never divide by 100 yourself. The minor-unit precision is currency-specific
(`MoneyFormatter::precisionFor()`); JPY has 0, USD/MYR have 2.
