---
title: Usage
---

# Usage

This page is the canonical entry point for `aiarmada/cashier-chip`. Start here, then drop into the
task-specific guides for the exact billing flow you need.

## 1. Add the billable trait

```php
<?php

namespace App\Models;

use AIArmada\CashierChip\Billing\Billable;
use AIArmada\CashierChip\Contracts\BillableContract;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements BillableContract
{
    use Billable;
}
```

If you also use `aiarmada/cashier`, keep the CHIP billable trait alongside the unified wrapper
trait.

## 2. Choose the right guide by task

- [Customers](05-customers.md) — create and manage CHIP customers
- [Charges](06-charges.md) — process one-off charges
- [Checkout](07-checkout.md) — redirect customers to hosted checkout
- [Payment methods](08-payment-methods.md) — save and manage recurring tokens
- [Subscriptions](09-subscriptions.md) — run recurring billing on application-managed renewals
- [Webhooks](10-webhooks.md) — configure and handle webhook-driven updates
- [Testing](11-testing.md) — test billing flows and webhook behavior
- [API reference](12-api-reference.md) — inspect the available methods in one place

## 3. Keep CHIP-specific ownership clear

- `aiarmada/cashier-chip` owns the `cashier_chip_*` tables and the CHIP billable APIs (customer links live in the `chip_customers` table; no billable columns needed)
- `aiarmada/chip` still owns the lower-level gateway API integration and purchase primitives
- `aiarmada/cashier` owns the gateway-agnostic wrapper when you need multi-gateway flows

## 4. Remember the renewal model

CHIP subscriptions are application-managed. If you use recurring billing, schedule the renewal
command and make sure it runs inside the correct owner context in multi-tenant apps. Renewal
attempts are owner-scoped records: they inherit ownership from the subscription, and direct
`RenewalAttempt` queries or writes must run inside that same owner context.

## 5. Use the Action classes

The package provides dedicated Action classes for common billing operations. These are the
canonical entry points for non-trivial workflows and are fully tested.

Coupon and promotion-code methods require the optional aiarmada/vouchers package. The
integration auto-detects that package by default and fails loudly if it is unavailable; see
[Configuration](03-configuration.md#integrations) for the explicit integration setting.

```php
use AIArmada\CashierChip\Actions\CancelChipSubscription;
use AIArmada\CashierChip\Actions\ChargeChipCustomer;
use AIArmada\CashierChip\Actions\ClaimRenewalAttempt;
use AIArmada\CashierChip\Actions\CreateChipSubscription;
use AIArmada\CashierChip\Actions\RefundChipPayment;
use AIArmada\CashierChip\Actions\SyncChipPurchaseStatus;

// One-off charge with rate limiting and error handling
ChargeChipCustomer::run($user, 2500, $recurringToken, ['reference' => 'Order #123']);

// Refund a purchase (full or partial)
$purchase = RefundChipPayment::run($purchaseId, 1000); // partial refund in cents

// Create a subscription from a builder
$subscription = CreateChipSubscription::run($builder, $recurringToken);

// Cancel a subscription immediately
CancelChipSubscription::run($subscription);

// Atomically claim a due renewal attempt before charging it
$attempt = ClaimRenewalAttempt::run($subscriptionId);

// Sync a purchase status from a webhook payload
SyncChipPurchaseStatus::run($user, $purchaseData, $payload);
SyncChipPurchaseStatus::make()->syncFailed($user, $purchaseData, $payload);
```

## 6. Console commands

`cashier-chip:renew-subscriptions` is the only command the package registers.

```bash
# Atomically claim and process due CHIP subscription renewals
php artisan cashier-chip:renew-subscriptions --dry-run
php artisan cashier-chip:renew-subscriptions --grace-hours=6
```

> **info**
> There is no dedicated webhook command. Webhook signature verification is configured entirely
> through `config/chip.php` (`collect.public_key`, `webhooks.verify_signature`,
> `webhooks.deduplication`) and is performed by the `aiarmada/chip` package — see
> `10-webhooks.md`. To inspect resolved config, use Laravel's own
> `php artisan config:show chip`.
