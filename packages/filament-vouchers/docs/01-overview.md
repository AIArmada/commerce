---
title: Overview
---

# Filament Vouchers

## Purpose

The `aiarmada/filament-vouchers` package is the Filament admin adapter for `aiarmada/vouchers`. It provides voucher management, usage monitoring, wallet administration, and voucher-focused configuration pages.

## What this package owns

- Filament resources for vouchers, voucher usage, and voucher wallets
- Voucher-focused configuration pages for stacking rules and targeting presets
- Voucher stats, redemption trends, and optional cart-aware admin integrations

## What this package does not own

- Voucher redemption logic, wallet bookkeeping, or usage persistence; those stay in `aiarmada/vouchers`
- Cart domain logic; it only integrates with Filament Cart when available
- Checkout or order orchestration

## Related packages

- [`aiarmada/vouchers`](../../vouchers/docs/01-overview.md) — core voucher domain package
- [`aiarmada/filament-cart`](../../filament-cart/docs/01-overview.md) — optional cart snapshot and voucher/cart workflow integration
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner-scoping helpers and shared contracts

## Main models services or surfaces

- **Resources** — voucher CRUD, voucher usage, and voucher wallet administration
- **Pages** — stacking configuration and targeting configuration
- **Widgets** — voucher stats, redemption trend charts, and optional cart integration widgets

## Owner scoping and security notes

- The plugin should respect the owner rules defined by `aiarmada/vouchers` and `commerce-support`
- Admin filters improve discoverability, but redemption and wallet mutations still need the core package to enforce owner-safe writes and eligibility checks

Filament admin panel plugin for managing vouchers, discounts, and promotional codes.

## Features

Filament Vouchers provides a complete admin interface for:

- **Voucher Management** – Create, edit, and monitor discount vouchers
- **Usage Tracking** – Track voucher redemptions with detailed history
- **Wallet System** – Allow users to save vouchers for later use
- **Cart Integration** – Seamless integration with Filament Cart package
- **Multi-tenant Support** – Owner-scoped resources for marketplace scenarios
- **Targeting Configuration** – Define preset targeting rules for vouchers
- **Stacking Rules** – Configure how vouchers combine with each other

## Requirements

- PHP 8.5+
- Laravel 13+
- Filament v5
- aiarmada/vouchers (core package)

## Package Architecture

```
filament-vouchers/
├── src/
│   ├── Actions/                    # Filament actions
│   │   ├── ActivateVoucherAction.php
│   │   ├── AddToMyWalletAction.php
│   │   ├── ApplyVoucherToCartAction.php
│   │   ├── BulkGenerateVouchersAction.php
│   │   ├── ManualRedeemVoucherAction.php
│   │   └── PauseVoucherAction.php
│   ├── Exports/
│   │   └── VoucherUsageExporter.php
│   ├── Extensions/
│   │   └── CartVoucherActions.php
│   ├── Integrations/
│   │   └── FilamentCartBridge.php
│   ├── Pages/
│   │   ├── StackingConfigurationPage.php
│   │   └── TargetingConfigurationPage.php
│   ├── Resources/
│   │   ├── VoucherResource.php
│   │   ├── VoucherUsageResource.php
│   │   ├── VoucherWalletResource.php
│   │   └── VoucherResource/       # Pages/Schemas/Tables/RelationManagers
│   ├── Support/
│   │   ├── ConditionTargetDisplay.php
│   │   ├── ConditionTargetFormData.php
│   │   ├── ConditionTargetPreset.php
│   │   ├── MoneyHelper.php
│   │   ├── OwnerTypeRegistry.php
│   │   └── VoucherStatsAggregator.php
│   ├── Widgets/
│   │   ├── VoucherStatsWidget.php
│   │   ├── RedemptionTrendChart.php
│   │   ├── AppliedVouchersWidget.php
│   │   ├── QuickApplyVoucherWidget.php
│   │   ├── VoucherCartStatsWidget.php
│   │   ├── VoucherSuggestionsWidget.php
│   │   ├── VoucherUsageTimelineWidget.php
│   │   └── VoucherWalletStatsWidget.php
│   ├── FilamentVouchersPlugin.php
│   └── FilamentVouchersServiceProvider.php
```

`FilamentVouchersPlugin` registers `VoucherStatsWidget` and
`RedemptionTrendChart` panel-wide. The remaining widgets are cart-integration
widgets registered through `Integrations/FilamentCartBridge` when
`aiarmada/filament-cart` is installed.

## Quick Start

1. Install the package:

```bash
composer require aiarmada/filament-vouchers
```

2. Register the plugin in your panel:

```php
use AIArmada\FilamentVouchers\FilamentVouchersPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentVouchersPlugin::make(),
        ]);
}
```

3. Run migrations (from core vouchers package):

```bash
php artisan migrate
```

4. Access the voucher resources in your Filament admin panel.

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Widgets](05-widgets.md)
- [Cart integration](06-cart-integration.md)
- [Troubleshooting](99-troubleshooting.md)
- [Core vouchers overview](../../vouchers/docs/01-overview.md)
