---
title: Filament Cashier CHIP Overview
---

# Filament Cashier CHIP

## Purpose

The `aiarmada/filament-cashier-chip` package is the Filament admin and customer-portal adapter for `aiarmada/cashier-chip`.

## What this package owns

- Filament resources for CHIP subscriptions, billable customers, and invoices
- Billing dashboard and customer-facing billing portal pages
- CHIP-specific subscription analytics widgets

## What this package does not own

- CHIP gateway APIs or subscription persistence; those stay in `aiarmada/cashier-chip` and `aiarmada/chip`
- Unified cross-gateway billing administration; that belongs to `aiarmada/filament-cashier`
- Tenant resolution itself; it consumes the owner context from the host app and `commerce-support`

## Related packages

- [`aiarmada/cashier-chip`](../../cashier-chip/docs/01-overview.md) — core CHIP billing layer
- [`aiarmada/chip`](../../chip/docs/01-overview.md) — direct CHIP gateway integration
- [`aiarmada/filament-cashier`](../../filament-cashier/docs/01-overview.md) — unified multi-gateway billing UI

## Main models services or surfaces

- **Resources** — subscriptions, customers, and invoices
- **Pages** — billing dashboard plus billing-portal pages for subscriptions, payment methods, and invoices
- **Widgets** — MRR, active subscribers, churn, trial conversions, attention required, revenue chart, and subscription distribution

## Owner scoping and security notes

- The plugin should mirror the owner-scoping behavior defined by `aiarmada/cashier-chip`
- When `filament-cashier` is also installed on the same panel, this plugin intentionally suppresses some overlapping resources and pages to avoid duplicate billing surfaces
- Portal and admin filters are not authorization; write operations still rely on the core billing packages to enforce owner-safe subscription and payment-method changes

Filament admin panel integration for Cashier CHIP - subscription billing with the CHIP payment gateway.

## Features

- **Subscription Management** – Full CRUD for local CHIP subscriptions
- **Customer Dashboard** – View and manage billable customers
- **Invoice Tracking** – Browse invoice history from CHIP purchases
- **Billing Portal** – Customer self-service for subscriptions and payment methods
- **Analytics Widgets** – MRR, churn rate, active subscribers, revenue charts
- **Multi-tenant Ready** – Owner-scoped queries via commerce-support

## Architecture

### Admin Resources

| Resource | Description |
|----------|-------------|
| `SubscriptionResource` | Manage all subscriptions with status tracking |
| `CustomerResource` | View billable models and their CHIP client info |
| `InvoiceResource` | Browse invoices from CHIP purchases |

### Billing Portal Pages

| Page | Description |
|------|-------------|
| `BillingDashboard` | Customer billing overview |
| `Subscriptions` | Manage active subscriptions |
| `PaymentMethods` | Add/remove payment methods |
| `Invoices` | Download invoice history |

### Dashboard Widgets

| Widget | Description |
|--------|-------------|
| `MRRWidget` | Monthly Recurring Revenue with trend |
| `ActiveSubscribersWidget` | Total active subscriber count |
| `ChurnRateWidget` | Monthly churn rate percentage |
| `TrialConversionsWidget` | Trial-to-paid conversion rate |
| `AttentionRequiredWidget` | Past due subscriptions count |
| `RevenueChartWidget` | Revenue trend over time |
| `SubscriptionDistributionWidget` | Subscriptions by plan |

## Quick Start

### Installation

```bash
composer require aiarmada/filament-cashier-chip
```

### Register Plugin

```php
use AIArmada\FilamentCashierChip\FilamentCashierChipPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentCashierChipPlugin::make(),
        ]);
}
```

### Publish Config

```bash
php artisan vendor:publish --tag=filament-cashier-chip-config
```

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament 5.x
- aiarmada/cashier-chip package

## Quick Links

| Guide | Description |
|-------|-------------|
| [Installation](02-installation.md) | Setup and configuration |
| [Configuration](03-configuration.md) | All config options |
| [Usage](04-usage.md) | Admin panel resources and common workflows |
| [Billing Portal](05-billing-portal.md) | Customer self-service |
| [Widgets](06-widgets.md) | Dashboard analytics |

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Billing Portal](05-billing-portal.md)
- [Widgets](06-widgets.md)
- [Troubleshooting](99-troubleshooting.md)
- [Core Cashier CHIP overview](../../cashier-chip/docs/01-overview.md)

---

**Ready?** Start with [Installation](02-installation.md) →
