---
title: Overview
---

# CHIP Payment Gateway Package

## Purpose

The `aiarmada/chip` package is the direct CHIP gateway integration for Commerce. It owns CHIP Collect payments, CHIP Send payouts, webhook processing, and CHIP-specific payment data.

## What this package owns

- CHIP Collect purchases, payments, clients, and refunds
- Subject-to-CHIP customer links used by billing packages
- CHIP Send bank accounts, payout instructions, payout limits, and payout webhooks
- CHIP webhook routing, signature verification, and webhook storage
- CHIP health checks, analytics, gateway registration, and docs integration hooks

## What this package does not own

- Unified multi-gateway billing abstractions; those belong to `aiarmada/cashier`
- Cashier-style CHIP subscriptions; those belong to `aiarmada/cashier-chip`
- Filament admin surfaces; those belong to `aiarmada/filament-chip`
- Checkout orchestration; that belongs to `aiarmada/checkout`

## Related packages

- [`aiarmada/filament-chip`](../../filament-chip/docs/01-overview.md) — Filament admin resources and analytics for CHIP data
- [`aiarmada/cashier-chip`](../../cashier-chip/docs/01-overview.md) — Cashier-style subscription billing on top of CHIP
- [`aiarmada/checkout`](../../checkout/docs/01-overview.md) — checkout orchestration that may use CHIP for payment collection
- [`aiarmada/docs`](../../docs/docs/01-overview.md) — optional, disabled-by-default document integration hooks registered by the package
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner scoping and payment gateway contracts

## Main models services or surfaces

- **Models** — CHIP purchases, payments, webhooks, bank accounts, clients, send instructions, send limits, send webhooks, and company statements
- **Services** — collect, send, customer directory, analytics, webhook, and gateway registration services
- **Actions** — reusable action classes for webhook dispatch, send instruction handling, document generation, and API record syncing
- **Support** — utility classes for customer bridging, owner tuple handling, payment status mapping, webhook purchase ID resolution, document data building, and webhook owner batch processing
- **Infrastructure** — webhook middleware, health-check commands, and payment gateway integration

## Owner scoping and security notes

- CHIP data is owner-aware and should follow the `commerce-support` owner-boundary rules
- Webhook routes and manual sync flows should resolve owner context safely before mutating purchases or payouts
- Explicit global or cross-owner access should remain a conscious opt-out, not an accidental side effect of missing owner context

A comprehensive Laravel integration for the [CHIP](https://chip-in.asia) payment gateway, providing both **Collect** (payment collection) and **Send** (payout) functionality for Malaysian businesses.

## What is CHIP?

CHIP is a Malaysian fintech payment gateway that offers:
- **FPX** (Financial Process Exchange) - Direct bank transfers
- **Credit/Debit Cards** - Visa, Mastercard, Maestro
- **E-Wallets** - DuitNow, Touch 'n Go, GrabPay, ShopeePay
- **Payouts** - Send money to bank accounts (CHIP Send)

## Package Features

### Payment Collection (CHIP Collect)
- Create and manage purchases with a fluent builder API
- Process one-time payments
- Pre-authorization and capture flows
- Full and partial refunds
- Real-time webhook handling with signature verification
- Client/customer management with saved payment methods
- Local customer directory linking any billable Eloquent subject to a CHIP client ID
- Idempotency support for preventing duplicate payments

### Payouts (CHIP Send)
- Create payout instructions to Malaysian bank accounts
- Manage recipient bank accounts
- Track payout status and history
- Webhook notifications for payout events

### Enterprise Features
- **Multi-tenancy Support**: Owner-scoped data with configurable isolation
- **Analytics**: Local analytics service with revenue metrics
- **Health Checks**: Built-in gateway health monitoring
- **Audit Trail**: Full audit logging via commerce-support integration
- **Testing Utilities**: Webhook simulation and testing helpers
- **Webhook Deduplication**: Automatic duplicate webhook prevention

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Your Laravel App                        │
├─────────────────────────────────────────────────────────────┤
│  Facades          │  Services           │  Gateway          │
│  ├─ Chip          │  ├─ ChipCollect     │  └─ ChipGateway   │
│  └─ ChipSend      │  ├─ ChipSend        │      (implements  │
│                   │  ├─ Webhook         │   PaymentGateway  │
│                   │  └─ Analytics       │     Interface)    │
├─────────────────────────────────────────────────────────────┤
│  Actions                                                     │
│  ├─ DispatchChipWebhookAction                                │
│  ├─ HandleSendInstructionWebhookAction                       │
│  ├─ RunChipPurchaseDocGenerationAction                       │
│  └─ SyncChipRecordsFromApiAction                             │
├─────────────────────────────────────────────────────────────┤
│  Support                                                     │
│  ├─ ChipCustomerBridge        ├─ ChipPaymentStatusMapper     │
│  ├─ ChipOwnerTuple            ├─ ResolveWebhookPurchaseId   │
│  ├─ ChipWebhookOwnerResolver  ├─ BuildChipDocData           │
│  └─ WebhookOwnerBatchRunner                                  │
├─────────────────────────────────────────────────────────────┤
│  Clients          │  Builders           │  Events           │
│  ├─ CollectClient │  └─ PurchaseBuilder │  ├─ PurchasePaid  │
│  └─ SendClient    │                     │  ├─ Refunded      │
│                   │                     │  └─ 20+ more...   │
├─────────────────────────────────────────────────────────────┤
│                      CHIP API (gate.chip-in.asia)           │
└─────────────────────────────────────────────────────────────┘
```

## Quick Start

```php
use AIArmada\Chip\Facades\Chip;

// Create a simple purchase
$purchase = Chip::purchase()
    ->email('customer@example.com')
    ->addProductCents('Premium Plan', 9900) // RM 99.00
    ->successUrl(route('payment.success'))
    ->failureUrl(route('payment.failed'))
    ->create();

// Redirect customer to payment page
return redirect($purchase->checkout_url);
```

## Requirements

- PHP 8.4+
- Laravel 13+
- CHIP merchant account with API credentials
- `aiarmada/commerce-support` package

## Multi-tenancy Support

Full multi-tenancy support via `commerce-support`:

- Owner-scoped models with `HasOwner` trait
- Auto-assignment on create
- Brand ID to owner mapping for webhooks
- Greppable opt-out via `withoutOwnerScope()`

## Related Packages

| Package | Description |
|---------|-------------|
| `aiarmada/filament-chip` | Filament admin panel for CHIP data |
| `aiarmada/cashier-chip` | Stripe Cashier-like subscription billing |
| `aiarmada/commerce-support` | Shared commerce contracts and utilities |

## Quick Links

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage Guide](04-usage.md)
- [Multitenancy](05-multitenancy.md)
- [CHIP Collect](chip-collect.md)
- [CHIP Send](chip-send.md)
- [Payment gateway](payment-gateway.md)
- [Webhooks](webhooks.md)
- [API reference](api-reference.md)
- [Troubleshooting](99-troubleshooting.md)
