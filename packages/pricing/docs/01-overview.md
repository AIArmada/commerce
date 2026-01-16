---
title: Overview
---

# Pricing Package

The `aiarmada/pricing` package provides a dynamic pricing engine for the AIArmada Commerce ecosystem. It enables flexible price management through price lists, tiered pricing, customer-specific pricing, and integration with the promotions package.

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
