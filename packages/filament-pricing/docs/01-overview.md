---
title: Overview
---

# Filament Pricing Package

## Purpose

The `aiarmada/filament-pricing` package is the Filament admin adapter for `aiarmada/pricing`. It exposes pricing management, settings, and simulator workflows through Filament resources and pages.

## What this package owns

- Filament resources for price lists and their related prices or tiers
- Pricing settings and simulator pages
- Pricing dashboard widgets and admin navigation
- Promotion-aware pricing statistics when `aiarmada/promotions` is installed

## What this package does not own

- Price calculation rules, persistence, or pricing settings storage; those stay in `aiarmada/pricing`
- Promotions administration provided by `aiarmada/filament-promotions`
- Product or customer domain records

## Related packages

- [`aiarmada/pricing`](../../pricing/docs/01-overview.md) — core pricing engine and data model
- [`aiarmada/promotions`](../../promotions/docs/01-overview.md) — promotion rules used by pricing
- [`aiarmada/filament-promotions`](../../filament-promotions/docs/01-overview.md) — dedicated promotions admin, when installed
- [`aiarmada/products`](../../products/docs/01-overview.md) and [`aiarmada/customers`](../../customers/docs/01-overview.md) — simulator context records

## Main models services or surfaces

- **Resources** — `PriceListResource`
- **Pages** — `ManagePricingSettings`, `PriceSimulator`
- **Widgets** — `PricingStatsWidget`
- **Relation managers** — `PricesRelationManager`, `TiersRelationManager`

## Owner scoping and security notes

- The package should follow the owner-scoping behavior defined by `aiarmada/pricing` and `commerce-support`
- Filament option lists improve usability, but submitted IDs in simulator or admin actions still need the backing domain package to enforce owner-safe reads and writes
- Promotions administration is owned by `aiarmada/filament-promotions`; this package only reports optional promotion statistics

## Features

- **Price List Management** - Full CRUD for price lists with scheduling and priority
- **Prices Relation Manager** - Manage individual prices within price lists
- **Price Tiers Relation Manager** - Configure quantity-based tier pricing
- **Promotion Statistics** - Optional promotion counts and usage statistics when `aiarmada/promotions` is installed
- **Price Simulator** - Interactive tool to test price calculations (requires `aiarmada/products`)
- **Pricing Settings Page** - Configure pricing defaults and features
- **Stats Widget** - Dashboard overview of active price lists and promotions
- **Multitenancy Support** - Full owner-scoped resource management

## Promotions UI Handoff

Promotion administration is provided by the dedicated `aiarmada/filament-promotions` package. Filament Pricing does not register a promotion resource; it may display promotion statistics when `aiarmada/promotions` is installed.

## Plugin Architecture

The package uses Filament's plugin architecture:

- **Resources**: `PriceListResource`
- **Pages**: `ManagePricingSettings`, `PriceSimulator`
- **Widgets**: `PricingStatsWidget`
- **Relation Managers**: `PricesRelationManager`, `TiersRelationManager`

## Navigation

All resources and pages are grouped under the "Pricing" navigation group:

| Item | Icon | Sort Order |
|------|------|------------|
| Price Lists | currency-dollar | 1 |
| Price Simulator | calculator | 99 |
| Pricing Settings | currency-dollar | 10 (Settings group) |

## Dependencies

### Required

- `aiarmada/pricing` - Core pricing engine
- `filament/filament` ^5.0 - Filament admin panel
- `filament/spatie-laravel-settings-plugin` ^5.0 - Settings management

### Optional

- `aiarmada/promotions` - For promotion management features
- `aiarmada/filament-promotions` - For the promotions admin surface
- `aiarmada/products` - For price simulator functionality
- `aiarmada/customers` - For customer-specific pricing in simulator

## Conditional Features

The plugin automatically enables features based on installed packages:

```php
// Promotion administration is registered by aiarmada/filament-promotions.

// PriceSimulator - only if products package is installed
if (class_exists('\\AIArmada\\Products\\Models\\Product')) {
    $pages[] = Pages\PriceSimulator::class;
}
```

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Resources](05-resources.md)
- [Pages and widgets](06-pages-widgets.md)
- [Multitenancy](07-multitenancy.md)
- [Core pricing overview](../../pricing/docs/01-overview.md)
