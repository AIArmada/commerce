# Executive Summary

> **Document:** 01 of 07  
> **Package:** `aiarmada/pricing` + `aiarmada/filament-pricing`  
> **Status:** Vision  
> **Last Updated:** December 2025

---

## Vision Statement

Establish the **Pricing Package** as the intelligent pricing engine of the AIArmada Commerce ecosystem—enabling dynamic, context-aware pricing through rules, tiers, customer segments, time-based promotions, and multi-currency support while maintaining a clean separation from the base product catalog.

---

## Strategic Position in Commerce Ecosystem

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     AIARMADA COMMERCE ECOSYSTEM                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│              ┌───────────────────┐                                       │
│              │     PRODUCTS      │                                       │
│              │   (Base Price)    │                                       │
│              └─────────┬─────────┘                                       │
│                        │                                                 │
│                        ▼                                                 │
│              ┌───────────────────┐                                       │
│              │     PRICING       │ ◄── THIS PACKAGE                      │
│              │  (Dynamic Rules)  │                                       │
│              └─────────┬─────────┘                                       │
│                        │                                                 │
│        ┌───────────────┼───────────────┐                                │
│        ▼               ▼               ▼                                │
│   ┌─────────┐    ┌───────────┐   ┌───────────┐                          │
│   │CUSTOMERS│    │   CART    │   │  ORDERS   │                          │
│   │(Segment)│    │ (Applied) │   │ (Snapshot)│                          │
│   └─────────┘    └───────────┘   └───────────┘                          │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Core Design Principles

### 1. **Overlay Architecture**
Pricing does not modify product base prices. It calculates adjustments as an overlay, allowing products to have simple, stable base prices while pricing handles complexity.

### 2. **Context-Aware**
Prices are calculated based on context: customer, quantity, time, channel, and geography.

### 3. **Rule Priority**
Multiple rules can apply; priority ordering determines which takes precedence or how they stack.

### 4. **Snapshot on Order**
When an order is placed, the calculated price is snapshotted. Future rule changes don't affect historical orders.

### 5. **Auditable**
All price calculations are logged, enabling debugging and compliance auditing.

---

## Package Responsibilities

| Responsibility | Description |
|----------------|-------------|
| **Price Lists** | Named collections of prices (Retail, Wholesale) |
| **Price Rules** | Condition-based price modifications |
| **Tiered Pricing** | Quantity breaks (buy more, save more) |
| **Customer Pricing** | Segment or customer-specific prices |
| **Time-Based Pricing** | Sale periods, flash sales |
| **Currency Conversion** | Multi-currency price calculation |
| **Bundle Pricing** | Complex bundle price calculations |
| **Price Calculation Engine** | Central calculation API |

---

## Package Non-Responsibilities

| Delegated To | Responsibility |
|--------------|----------------|
| `products` | Base price storage |
| `vouchers` | Coupon/discount codes (post-calculation) |
| `cart` | Price application and display |
| `tax` | Tax calculation (post-pricing) |

---

## Price Calculation Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        PRICE CALCULATION FLOW                            │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌──────────────┐                                                       │
│   │ Base Price   │  $100.00 (from Product)                              │
│   └──────┬───────┘                                                       │
│          │                                                               │
│          ▼                                                               │
│   ┌──────────────┐                                                       │
│   │ Price List   │  Wholesale: $85.00 (15% off for B2B)                 │
│   └──────┬───────┘                                                       │
│          │                                                               │
│          ▼                                                               │
│   ┌──────────────┐                                                       │
│   │ Tiered Price │  Buy 10+: $80.00 per unit                            │
│   └──────┬───────┘                                                       │
│          │                                                               │
│          ▼                                                               │
│   ┌──────────────┐                                                       │
│   │ Time-Based   │  Summer Sale: Additional 10% off                     │
│   └──────┬───────┘                                                       │
│          │                                                               │
│          ▼                                                               │
│   ┌──────────────┐                                                       │
│   │ Final Price  │  $72.00 per unit                                     │
│   └──────────────┘                                                       │
│                                                                          │
│   (Vouchers/coupons apply AFTER this in Cart)                           │
│   (Tax applies AFTER vouchers)                                          │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Core Models

### PriceList Model
```php
namespace AIArmada\Pricing\Models;

class PriceList extends Model
{
    protected $fillable = [
        'name',           // "Retail", "Wholesale", "VIP"
        'slug',
        'currency',       // "MYR", "USD"
        'priority',       // Higher = takes precedence
        'is_default',
        'starts_at',
        'ends_at',
    ];
    
    public function prices(): HasMany;
    public function segments(): BelongsToMany; // Which customer segments see this
}
```

### PriceRule Model
```php
namespace AIArmada\Pricing\Models;

class PriceRule extends Model
{
    protected $fillable = [
        'name',
        'type',           // RuleType::Percentage, Fixed, Formula
        'value',          // -10 (10% off), or formula string
        'conditions',     // JSON conditions
        'priority',
        'is_stackable',   // Can combine with other rules
        'starts_at',
        'ends_at',
    ];
    
    public function products(): BelongsToMany;
    public function categories(): BelongsToMany;
    public function segments(): BelongsToMany;
}
```

### TieredPrice Model
```php
namespace AIArmada\Pricing\Models;

class TieredPrice extends Model
{
    protected $fillable = [
        'priceable_type',
        'priceable_id',
        'min_quantity',
        'max_quantity',
        'price',           // Fixed price at this tier
        'discount_percent', // Or percentage discount
        'price_list_id',
    ];
}
```

---

## Pricing Engine API

```php
namespace AIArmada\Pricing;

class PricingEngine
{
    public function calculate(
        BuyableInterface $product,
        ?Customer $customer = null,
        int $quantity = 1,
        ?string $currency = null
    ): PriceResult;
    
    public function getPriceBreaks(BuyableInterface $product): Collection;
    
    public function getApplicableRules(
        BuyableInterface $product,
        ?Customer $customer = null
    ): Collection;
    
    public function explain(
        BuyableInterface $product,
        ?Customer $customer = null,
        int $quantity = 1
    ): PriceExplanation; // For debugging
}
```

---

## Vision Documents

| # | Document | Focus |
|---|----------|-------|
| 01 | Executive Summary | This document |
| 02 | [Price Lists](02-price-lists.md) | Named price collections |
| 03 | [Tiered Pricing](03-tiered-pricing.md) | Quantity breaks |
| 04 | [Price Rules](04-price-rules.md) | Condition-based rules |
| 05 | [Multi-Currency](05-multi-currency.md) | Currency conversion |
| 06 | [Database Schema](06-database-schema.md) | Tables, relationships |
| 07 | [Implementation Roadmap](07-implementation-roadmap.md) | Phased delivery |

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Test Coverage | 90%+ |
| PHPStan Level | 6 |
| Price Lists | Unlimited |
| Tier Levels | Unlimited |
| Currency Support | Configurable |
| Calculation Speed | <10ms |

---

## Dependencies

### Required
| Package | Purpose |
|---------|---------|
| `aiarmada/commerce-support` | Shared interfaces |
| `akaunting/laravel-money` | Currency handling |

### Optional (Auto-Integration)
| Package | Integration When Present |
|---------|--------------------------|
| `aiarmada/products` | Product price calculation |
| `aiarmada/customers` | Customer segment pricing |
| `aiarmada/cart` | Cart price application |
| `aiarmada/orders` | Price snapshotting |

---

## Navigation

**Next:** [02-price-lists.md](02-price-lists.md)
