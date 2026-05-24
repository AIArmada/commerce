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
- The fallback promotions admin resource when `aiarmada/promotions` is installed without `aiarmada/filament-promotions`

## What this package does not own

- Price calculation rules, persistence, or pricing settings storage; those stay in `aiarmada/pricing`
- Dedicated promotions admin when `aiarmada/filament-promotions` is installed
- Product or customer domain records

## Related packages

- [`aiarmada/pricing`](../../pricing/docs/01-overview.md) — core pricing engine and data model
- [`aiarmada/promotions`](../../promotions/docs/01-overview.md) — promotion rules used by pricing
- [`aiarmada/filament-promotions`](../../filament-promotions/docs/01-overview.md) — dedicated promotions admin, when installed
- [`aiarmada/products`](../../products/docs/01-overview.md) and [`aiarmada/customers`](../../customers/docs/01-overview.md) — simulator context records

## Main models services or surfaces

- **Resources** — `PriceListResource`, plus fallback `PromotionResource` only when the dedicated promotions UI is absent
- **Pages** — `ManagePricingSettings`, `PriceSimulator`
- **Widgets** — `PricingStatsWidget`
- **Relation managers** — `PricesRelationManager`, `TiersRelationManager`

## Owner scoping and security notes

- The package should follow the owner-scoping behavior defined by `aiarmada/pricing` and `commerce-support`
- Filament option lists improve usability, but submitted IDs in simulator or admin actions still need the backing domain package to enforce owner-safe reads and writes
- Promotions UI ownership is explicit: fallback mode belongs to this package only until `aiarmada/filament-promotions` is present

## Features

- **Price List Management** - Full CRUD for price lists with scheduling and priority
- **Prices Relation Manager** - Manage individual prices within price lists
- **Price Tiers Relation Manager** - Configure quantity-based tier pricing
- **Promotion Management** - Fallback promotion admin when `aiarmada/promotions` is installed and `aiarmada/filament-promotions` is not
- **Price Simulator** - Interactive tool to test price calculations (requires `aiarmada/products`)
- **Pricing Settings Page** - Configure pricing defaults and features
- **Stats Widget** - Dashboard overview of active price lists and promotions
- **Multitenancy Support** - Full owner-scoped resource management

## Promotions UI Handoff

The pricing plugin supports two valid promotions UI modes:

- **Fallback mode** — when only `aiarmada/promotions` is installed, Filament Pricing registers its legacy `PromotionResource` under the Pricing navigation.
- **Dedicated mode** — when `aiarmada/filament-promotions` is also installed, the dedicated promotions plugin owns the promotions navigation and resource. Filament Pricing skips its fallback `PromotionResource` to avoid duplicate admin surfaces.

## Plugin Architecture

The package uses Filament's plugin architecture:

- **Resources**: `PriceListResource`, `PromotionResource` (fallback only)
- **Pages**: `ManagePricingSettings`, `PriceSimulator`
- **Widgets**: `PricingStatsWidget`
- **Relation Managers**: `PricesRelationManager`, `TiersRelationManager`

## Navigation

All resources and pages are grouped under the "Pricing" navigation group:

| Item | Icon | Sort Order |
|------|------|------------|
| Price Lists | currency-dollar | 1 |
| Promotions (fallback only) | gift | 2 |
| Price Simulator | calculator | 99 |
| Pricing Settings | currency-dollar | 10 (Settings group) |

## Dependencies

### Required

- `aiarmada/pricing` - Core pricing engine
- `filament/filament` ^5.0 - Filament admin panel
- `filament/spatie-laravel-settings-plugin` ^5.0 - Settings management

### Optional

- `aiarmada/promotions` - For promotion management features
- `aiarmada/filament-promotions` - For the dedicated promotions admin surface
- `aiarmada/products` - For price simulator functionality
- `aiarmada/customers` - For customer-specific pricing in simulator

## Conditional Features

The plugin automatically enables features based on installed packages:

```php
// PromotionResource - only in fallback mode
$hasDedicatedPromotionsPlugin = class_exists('\\AIArmada\\FilamentPromotions\\FilamentPromotionsPlugin');

if (class_exists('\\AIArmada\\Promotions\\Models\\Promotion') && ! $hasDedicatedPromotionsPlugin) {
    $resources[] = Resources\PromotionResource::class;
}

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
