---
title: Overview
---

# Filament Pricing Package

The `aiarmada/filament-pricing` package provides a Filament v5 admin panel for managing pricing rules, price lists, promotions, and pricing settings in the AIArmada Commerce ecosystem.

## Features

- **Price List Management** - Full CRUD for price lists with scheduling and priority
- **Prices Relation Manager** - Manage individual prices within price lists
- **Price Tiers Relation Manager** - Configure quantity-based tier pricing
- **Promotion Management** - Create and manage promotional discounts (requires `aiarmada/promotions`)
- **Price Simulator** - Interactive tool to test price calculations (requires `aiarmada/products`)
- **Pricing Settings Page** - Configure pricing defaults and features
- **Stats Widget** - Dashboard overview of active price lists and promotions
- **Multitenancy Support** - Full owner-scoped resource management

## Plugin Architecture

The package uses Filament's plugin architecture:

- **Resources**: `PriceListResource`, `PromotionResource`
- **Pages**: `ManagePricingSettings`, `PriceSimulator`
- **Widgets**: `PricingStatsWidget`
- **Relation Managers**: `PricesRelationManager`, `TiersRelationManager`

## Navigation

All resources and pages are grouped under the "Pricing" navigation group:

| Item | Icon | Sort Order |
|------|------|------------|
| Price Lists | currency-dollar | 1 |
| Promotions | gift | 2 |
| Price Simulator | calculator | 99 |
| Pricing Settings | currency-dollar | 10 (Settings group) |

## Dependencies

### Required

- `aiarmada/pricing` - Core pricing engine
- `filament/filament` ^5.0 - Filament admin panel
- `filament/spatie-laravel-settings-plugin` ^5.0 - Settings management

### Optional

- `aiarmada/promotions` - For promotion management features
- `aiarmada/products` - For price simulator functionality
- `aiarmada/customers` - For customer-specific pricing in simulator

## Conditional Features

The plugin automatically enables features based on installed packages:

```php
// PromotionResource - only if promotions package is installed
if (class_exists('\\AIArmada\\Promotions\\Models\\Promotion')) {
    $resources[] = Resources\PromotionResource::class;
}

// PriceSimulator - only if products package is installed
if (class_exists('\\AIArmada\\Products\\Models\\Product')) {
    $pages[] = Pages\PriceSimulator::class;
}
```
