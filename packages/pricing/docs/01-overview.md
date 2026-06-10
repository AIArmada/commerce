---
title: Overview
---

# Pricing Package

## Purpose

The `aiarmada/pricing` package owns reusable pricing rules, price lists, tiered pricing, and pricing settings for the Commerce ecosystem.

## What this package owns

- Price lists, individual price entries, and quantity-based price tiers
- Priority-based price resolution across customer, segment, tier, promotional, list, and base-price sources
- Pricing runtime settings, including `PricingSettings` and `PromotionalPricingSettings`
- Owner-scoped pricing records and shared/global pricing behavior when configured

## What this package does not own

- Product or variant catalog records (`aiarmada/products`)
- Customer master data (`aiarmada/customers`)
- Promotion rule definitions and campaign lifecycle (`aiarmada/promotions`)
- Filament admin surfaces (`aiarmada/filament-pricing`)

## Related packages

- [`aiarmada/filament-pricing`](../../filament-pricing/docs/01-overview.md) — Filament resources, settings page, and simulator UI for pricing
- [`aiarmada/promotions`](../../promotions/docs/01-overview.md) — promotion engine used during price resolution
- [`aiarmada/products`](../../products/docs/01-overview.md) — common priceable catalog records
- [`aiarmada/customers`](../../customers/docs/01-overview.md) — customer and segment context for personalized pricing

## Main models services or surfaces

- **Models** — `PriceList`, `Price`, `PriceTier`
- **Actions** — `ResolveBasePrice`, `ResolveTierPrice`, `FormatPriceForDisplay`, `ApplyPromotionalAdjustment`
- **Contracts** — `PriceCalculatorInterface`, `Priceable`, `CustomerPriceResolverInterface`, `SegmentPriceResolverInterface`, `TierResolverInterface`
- **Support** — `CustomerPriceResolver`, `SegmentPriceResolver`, `TierResolver`, `PromotionalPriceResolver`, `PricingIntegrationRegistrar`, `PricingOwnerScope`
- **Events** — `PriceCalculated`, `TierApplied`
- **Settings** — `PricingSettings`, `PromotionalPricingSettings`
- **Core surface** — the pricing resolution pipeline that decides which price source wins for a given context

## Owner scoping and security notes

- Owner enforcement is configured through `pricing.features.owner.enabled`, `pricing.features.owner.include_global`, and `pricing.features.owner.auto_assign_on_create`
- Global pricing rows are explicit shared records, not a fallback for missing owner context
- Call sites that submit customer, segment, or priceable identifiers should resolve them inside the current owner scope before reading or writing pricing records

## Features

- **Price Lists** - Create multiple price lists for different customer segments, regions, or sales channels
- **Tiered Pricing** - Quantity-based pricing with configurable tier ranges
- **Customer-Specific Pricing** - Assign special prices to individual customers
- **Segment Pricing** - Price differentiation based on customer segments
- **Promotional Integration** - Seamlessly integrates with the promotions package for discounts
- **Time-Based Pricing** - Schedule price changes with start and end dates
- **Multi-Currency Support** - Handle prices in multiple currencies
- **Multitenancy Support** - Full owner-scoping for multi-tenant applications
- **Activity Logging** - Track all price changes with Spatie Activity Log

## Architecture

The pricing engine follows a priority-based calculation system:

1. **Customer-Specific Price** - Highest priority, unique price for a specific customer
2. **Segment Price** - Prices assigned to customer segments
3. **Tier Pricing** - Quantity-based pricing tiers
4. **Promotional Price** - Active promotions that apply to the item
5. **Price List Price** - Default or assigned price list
6. **Base Price** - Fallback to the item's base price

## Models

| Model | Description |
|-------|-------------|
| `PriceList` | Represents a collection of prices (e.g., Retail, Wholesale, VIP) |
| `Price` | Individual price entry for a priceable item within a price list |
| `PriceTier` | Quantity-based pricing tier for volume discounts |

## Dependencies

- `aiarmada/commerce-support` - Core commerce utilities and multitenancy support
- `spatie/laravel-settings` - Settings management
- `spatie/laravel-activitylog` - Activity logging

## Optional Integrations

- `aiarmada/promotions` - For promotional pricing and discounts
- `aiarmada/products` - For product/variant pricing
- `aiarmada/customers` - For customer-specific pricing

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Models](05-models.md)
- [Multitenancy](06-multitenancy.md)
- [Filament Pricing overview](../../filament-pricing/docs/01-overview.md)
