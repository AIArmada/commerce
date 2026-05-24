---
title: Overview
---

# Filament CHIP

## Purpose

The `aiarmada/filament-chip` package is the Filament admin adapter for `aiarmada/chip`.

## What this package owns

- Filament resources and pages for CHIP purchases and clients
- CHIP analytics dashboard and essential transaction widgets
- CHIP-focused admin visibility into transaction and client data

## What this package does not own

- CHIP API integration, purchase creation, payouts, or webhook processing; those stay in `aiarmada/chip`
- Cashier-style subscription billing or customer self-service billing portal flows; those belong to `aiarmada/cashier-chip` and `aiarmada/filament-cashier-chip`
- Checkout orchestration

## Related packages

- [`aiarmada/chip`](../../chip/docs/01-overview.md) — core CHIP gateway package
- [`aiarmada/cashier-chip`](../../cashier-chip/docs/01-overview.md) — CHIP subscription billing layer
- [`aiarmada/filament-cashier-chip`](../../filament-cashier-chip/docs/01-overview.md) — CHIP billing portal and subscription UI

## Main models services or surfaces

- **Resources** — purchase and client administration by default
- **Pages** — analytics dashboard
- **Widgets** — stats, revenue chart, and recent transactions
- **Optional surfaces** — payment, bank account, payout, and statement resources available outside the default registration set

## Owner scoping and security notes

- The plugin should mirror the owner-scoping behavior defined by `aiarmada/chip`
- Admin filtering is not authorization; actions and detail lookups still rely on the core CHIP package to enforce owner-safe reads and writes

A Filament admin panel plugin for managing CHIP payment gateway data. Provides essential visualization, analytics, and management interfaces for purchases and clients.

## Key Features

- **Essential Resources** - Purchase and Client management (more available optionally)
- **Analytics Dashboard** - Revenue metrics and transaction insights
- **Key Widgets** - Revenue charts, stats, recent transactions
- **Owner Scoping** - Multi-tenancy ready with owner-based isolation
- **Customizable** - Override any resource, page, or widget

> **Note**: For subscription billing and customer self-service portal, use `filament-cashier-chip` package.

## Architecture

### Plugin-Based Registration

Register via Filament's plugin system:

```php
use AIArmada\FilamentChip\FilamentChipPlugin;

$panel->plugin(FilamentChipPlugin::make());
```

### Component Discovery

The plugin registers by default:
- **2 Resources**: `PurchaseResource`, `ClientResource`
- **1 Page**: `AnalyticsDashboardPage`
- **3 Widgets**: `ChipStatsWidget`, `RevenueChartWidget`, `RecentTransactionsWidget`

Additional resources and widgets are available in the package but not registered by default.

## Available Resources

| Resource | Registered | Description |
|----------|------------|-------------|
| `PurchaseResource` | ✅ Default | Payment transactions with status, refunds, capture |
| `ClientResource` | ✅ Default | Customer records from CHIP |
| `PaymentResource` | Optional | Individual payment records |
| `BankAccountResource` | Optional | Payout recipient bank accounts |
| `SendInstructionResource` | Optional | Payout instructions |
| `CompanyStatementResource` | Optional | Company account statements |

## Available Pages

| Page | Registered | Description |
|------|------------|-------------|
| `AnalyticsDashboardPage` | ✅ Default | Revenue analytics with period filtering |

## Available Widgets

| Widget | Registered | Description |
|--------|------------|-------------|
| `ChipStatsWidget` | ✅ Default | Total revenue, transaction count, avg value |
| `RevenueChartWidget` | ✅ Default | Revenue over time chart |
| `RecentTransactionsWidget` | ✅ Default | Latest purchases table |
| `AccountBalanceWidget` | Optional | CHIP account balance |
| `AccountTurnoverWidget` | Optional | Account turnover stats |
| `PayoutStatsWidget` | Optional | Payout statistics |
| `RecentPayoutsWidget` | Optional | Latest payouts table |

## Requirements

- PHP 8.4+
- Laravel 13+
- Filament 5.0+
- `aiarmada/chip` (core package)

## Quick Start

```bash
# Install
composer require aiarmada/filament-chip

# Publish config (optional)
php artisan vendor:publish --tag="filament-chip-config"
```

```php
// app/Providers/Filament/AdminPanelProvider.php
use AIArmada\FilamentChip\FilamentChipPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->plugin(FilamentChipPlugin::make());
}
```

## Related Packages

| Package | Description |
|---------|-------------|
| `aiarmada/chip` | Core CHIP payment gateway integration |
| `aiarmada/cashier-chip` | Subscription billing with CHIP |
| `aiarmada/filament-cashier-chip` | Filament billing portal and subscription UI |

## Documentation

- [Installation](02-installation.md) - Setup and configuration
- [Configuration](03-configuration.md) - Full config reference  
- [Usage](04-usage.md) - Resource customization and admin workflows
- [Pages & Widgets](05-pages-widgets.md) - Dashboard components
- [Troubleshooting](99-troubleshooting.md) - Common issues
- [Core CHIP overview](../../chip/docs/01-overview.md)
