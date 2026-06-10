---
title: Overview
---

# Cashier CHIP

## Purpose

The `aiarmada/cashier-chip` package provides Cashier-style recurring billing and billable-model
patterns on top of `aiarmada/chip`.

## What this package owns

- CHIP-specific billable columns and subscription tables
- Cashier-style customer, charge, checkout, payment method, and subscription APIs for CHIP
- Application-managed subscription renewals and recurring-token billing flows
- CHIP billing webhooks and test utilities for the Cashier-style layer

## What this package does not own

- Direct CHIP gateway APIs and purchase models; those stay in `aiarmada/chip`
- Unified multi-gateway billing abstraction; that belongs to `aiarmada/cashier`
- Filament admin or customer-facing billing portal surfaces; those belong to `aiarmada/filament-cashier-chip`

## Related packages

- [`aiarmada/chip`](../../chip/docs/01-overview.md) — core CHIP gateway integration
- [`aiarmada/cashier`](../../cashier/docs/01-overview.md) — unified multi-gateway wrapper
- [`aiarmada/filament-cashier-chip`](../../filament-cashier-chip/docs/01-overview.md) — Filament admin and billing portal UI
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner scoping and shared primitives

## Main models services or surfaces

- **Actions** — `ChargeChipCustomer`, `RefundChipPayment`, `CreateChipSubscription`, `CancelChipSubscription`, `SyncChipPurchaseStatus` — canonical entry points for billing operations
- **Billable surface** — trait-based customer, payment method, checkout, charge, and subscription APIs
- **Persistence** — `cashier_chip_*` subscription tables plus CHIP billable columns
- **Runtime behavior** — application-managed renewals (via `RenewSubscriptionsCommand` with `OwnerBatchRunner`), webhook processing, and local billing workflows
- **Testing surface** — helpers and patterns for billing flows, recurring tokens, and webhook handling

## Directory structure

```
src/
├── Actions/          # ChargeChipCustomer, RefundChipPayment, CreateChipSubscription,
│                     # CancelChipSubscription, SyncChipPurchaseStatus
├── Billing/          # Billable, Cashier, Checkout, Coupon, Discount, PromotionCode
├── Payment/          # Payment, PaymentMethod, PaymentMethodStore, StoredPaymentMethod,
│                     # InvoicePayment
├── Subscription/    # Subscription, SubscriptionBuilder, SubscriptionItem
├── Invoice/         # Invoice, InvoiceLineItem
├── Console/         # RenewSubscriptionsCommand, WebhookCommand
├── Contracts/       # BillableContract, etc.
├── Events/          # SubscriptionCreated, PaymentSucceeded, etc.
├── Exceptions/
├── Listeners/
├── Testing/         # Test utilities
└── CashierChipServiceProvider.php

tests/
└── Actions/         # Test suite for all five Actions
```

## Owner scoping and security notes

- Cashier CHIP should mirror the owner-scoping behavior of `aiarmada/chip` and `commerce-support`
- Renewals, webhook callbacks, and customer lookups should re-enter the correct owner context before mutating subscriptions or payment methods

## Key CHIP differences

| Feature | Stripe | CHIP |
| --- | --- | --- |
| Subscription management | Gateway-managed | Application-managed |
| Saved payment methods | Payment methods | Recurring tokens |
| Billing portal | Hosted by Stripe | Self-hosted via Filament |
| Setup flow | Setup intents | Zero-amount purchases |

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Customers](05-customers.md)
- [Charges](06-charges.md)
- [Checkout](07-checkout.md)
- [Payment methods](08-payment-methods.md)
- [Subscriptions](09-subscriptions.md)
- [Webhooks](10-webhooks.md)
- [Testing](11-testing.md)
- [API reference](12-api-reference.md)
- [Troubleshooting](99-troubleshooting.md)
- [Filament Cashier CHIP overview](../../filament-cashier-chip/docs/01-overview.md)
