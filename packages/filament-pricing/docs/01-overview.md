---
title: Overview
---

# Filament Pricing Package

The `aiarmada/filament-pricing` package provides a Filament v5 admin panel for managing price lists, pricing settings, simulator workflows, and an optional fallback promotions resource in the AIArmada Commerce ecosystem.

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
