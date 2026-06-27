---
title: Usage
---

# Usage

This page is the canonical entry point for `aiarmada/cashier`. Use it to orient yourself before
dropping into the deeper gateway-specific guides.

## 1. Make the billable model gateway-aware

Add the wrapper trait and the traits from the gateway packages you actually install:

```php
<?php

namespace App\Models;

use AIArmada\Cashier\Concerns\Billable as CashierBillable;
use AIArmada\CashierChip\Billable as ChipBillable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Cashier\Billable as StripeBillable;

class User extends Authenticatable
{
    use StripeBillable, ChipBillable, CashierBillable;
}
```

## 2. Pick the gateway explicitly when the flow matters

```php
$subscription = $user->newGatewaySubscription('default', 'price_monthly', 'stripe')
    ->create($stripePaymentMethod);

$payment = $user->chargeWithGateway(5000, $chipPaymentMethod, 'chip');
```

Explicit gateway selection is easier to reason about in cross-gateway apps than relying on the
default driver everywhere.

## 3. Use the deeper guides by task

- [Subscriptions](05-subscriptions.md) — recurring billing APIs and status helpers
- [Payments](06-payments.md) — one-off charges, checkout, payment methods, and invoices
- [Multi-gateway](07-multi-gateway.md) — selecting, mixing, and querying multiple gateways
- [Webhooks](08-webhooks.md) — gateway webhook ownership and unified events

## 4. Billable trait

```php
use AIArmada\Cashier\Concerns\Billable as CashierBillable;

class User extends Authenticatable
{
    use StripeBillable, ChipBillable, CashierBillable;
}
```

The `Concerns\Billable` trait is the package-owned gateway management entrypoint. Add it alongside the gateway-specific traits for the gateways you install.

## 5. Contracts-to-implementations matrix

12 contracts × 2 gateways (Stripe, CHIP) = 24 implementations. Use this matrix to verify a new gateway covers all contracts:

| Contract | Stripe | CHIP |
|---|---|---|
| `GatewayContract` | `StripeGateway` | `ChipGateway` |
| `BillableContract` | Stripe native | Cashier-chip |
| `CheckoutContract` | `StripeCheckout` | `ChipCheckout` |
| `CheckoutBuilderContract` | `StripeCheckoutBuilder` | `ChipCheckoutBuilder` |
| `CustomerContract` | `StripeCustomer` | `ChipCustomer` |
| `PaymentContract` | `StripePayment` | `ChipPayment` |
| `PaymentMethodContract` | `StripePaymentMethod` | `ChipPaymentMethod` |
| `InvoiceContract` | `StripeInvoice` | `ChipInvoice` |
| `InvoiceLineItemContract` | `StripeInvoiceLineItem` | `ChipInvoiceLineItem` |
| `SubscriptionContract` | `StripeSubscription` | `ChipSubscription` |
| `SubscriptionItemContract` | `StripeSubscriptionItem` | `ChipSubscriptionItem` |
| `SubscriptionBuilderContract` | `StripeSubscriptionBuilder` | `ChipSubscriptionBuilder` |

Every new gateway must implement all 12 contracts. Missing implementations will break at runtime.

## 6. Remember what this package does not own

- `laravel/cashier` still owns Stripe tables, controllers, and Stripe-native features
- `aiarmada/cashier-chip` still owns CHIP billing persistence and renewals
- `aiarmada/filament-cashier` owns Filament billing UIs

Use `aiarmada/cashier` as the common interface first, then drop down to the gateway packages only
when you need gateway-native behavior.
