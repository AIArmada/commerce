# Executive Summary

> **Document:** 01 of 08  
> **Package:** `aiarmada/products` + `aiarmada/filament-products`  
> **Status:** Vision  
> **Last Updated:** December 2025

---

## Vision Statement

Establish the **Products Package** as the foundational catalog layer of the AIArmada Commerce ecosystem—a highly extensible, variant-aware, and integration-ready product information management (PIM) system that serves as the single source of truth for all purchasable entities.

---

## Strategic Position in Commerce Ecosystem

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     AIARMADA COMMERCE ECOSYSTEM                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│                         ┌───────────────────┐                            │
│                         │     PRODUCTS      │ ◄── THIS PACKAGE           │
│                         │   (The Catalog)   │                            │
│                         └─────────┬─────────┘                            │
│                                   │                                      │
│           ┌───────────────────────┼───────────────────────┐              │
│           │                       │                       │              │
│           ▼                       ▼                       ▼              │
│   ┌───────────────┐       ┌───────────────┐       ┌───────────────┐      │
│   │   INVENTORY   │       │     CART      │       │    PRICING    │      │
│   │ (Stock Levels)│       │ (Shopping)    │       │ (Price Rules) │      │
│   └───────────────┘       └───────┬───────┘       └───────────────┘      │
│                                   │                                      │
│                                   ▼                                      │
│                           ┌───────────────┐                              │
│                           │    ORDERS     │                              │
│                           │ (Transaction) │                              │
│                           └───────┬───────┘                              │
│                                   │                                      │
│           ┌───────────────────────┼───────────────────────┐              │
│           ▼                       ▼                       ▼              │
│   ┌───────────────┐       ┌───────────────┐       ┌───────────────┐      │
│   │    CASHIER    │       │   SHIPPING    │       │   VOUCHERS    │      │
│   │  (Payments)   │       │ (Fulfillment) │       │  (Discounts)  │      │
│   └───────────────┘       └───────────────┘       └───────────────┘      │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Core Design Principles

### 1. **Interface-First Integration**
Products implement `BuyableInterface` (from Cart), `InventoryableInterface` (from Inventory), and `PurchasableInterface` (from commerce-support) enabling seamless cross-package communication without hard dependencies.

### 2. **Variant Architecture**
Support for complex product structures:
- Simple products (single SKU)
- Configurable products (size/color variants)
- Bundle products (grouped items)
- Digital products (downloadable assets)
- Subscription products (recurring billing integration with Cashier)

### 3. **Multi-Tenant Aware**
Products are scoped by owner/tenant enabling multi-store, multi-brand architectures.

### 4. **Media-Rich**
Integration with Spatie Media Library for product images, galleries, and digital assets.

### 5. **SEO-Ready**
Slugs, meta descriptions, and structured data support out of the box.

---

## Package Responsibilities

| Responsibility | Description |
|----------------|-------------|
| **Product Definition** | Name, description, SKU, status, visibility |
| **Variant Management** | Options (Size, Color), option values, variant combinations |
| **Category Hierarchy** | Nested categories with unlimited depth |
| **Collections** | Manual and rule-based product groupings |
| **Attributes** | Custom product attributes (material, weight, dimensions) |
| **Media Management** | Images, galleries, digital files |
| **Pricing** | Base price (advanced pricing delegated to `pricing` package) |
| **Cross-Package Events** | ProductCreated, ProductUpdated, ProductDeleted, VariantOutOfStock |

---

## Package Non-Responsibilities

| Delegated To | Responsibility |
|--------------|----------------|
| `inventory` | Stock levels, allocations, movements |
| `pricing` | Tiered pricing, customer-specific pricing, promotions |
| `cart` | Shopping cart operations |
| `orders` | Purchase history, order items |
| `cashier` | Subscription product billing |
| `affiliates` | Commission rules per product |
| `tax` | Tax class assignments and calculations |

---

## Integration Points

### With Cart Package
```php
// Product implements BuyableInterface
class Product extends Model implements BuyableInterface
{
    public function getBuyableIdentifier(): string;
    public function getBuyableName(): string;
    public function getBuyablePrice(): Money;
    public function canBePurchased(?int $quantity = null): bool;
}
```

### With Inventory Package
```php
// Product implements InventoryableInterface
class Variant extends Model implements InventoryableInterface
{
    public function inventoryLevels(): MorphMany;
    public function getTotalOnHand(): int;
    public function getTotalAvailable(): int;
}
```

### With Cashier Package
```php
// Subscription products link to Cashier prices
class Product extends Model
{
    public function isSubscription(): bool;
    public function getStripePrice(?string $interval = null): ?string;
    public function getChipPrice(?string $interval = null): ?string;
}
```

---

## Vision Documents

| # | Document | Focus |
|---|----------|-------|
| 01 | Executive Summary | This document |
| 02 | [Product Architecture](02-product-architecture.md) | Core models, types, states |
| 03 | [Variant System](03-variant-system.md) | Options, combinations, SKU generation |
| 04 | [Category & Collections](04-categories-collections.md) | Hierarchies, rules, navigation |
| 05 | [Attributes & Specifications](05-attributes.md) | Custom fields, filterable attributes |
| 06 | [Cross-Package Integration](06-integration.md) | Interfaces, events, listeners |
| 07 | [Database Schema](07-database-schema.md) | Tables, indexes, relationships |
| 08 | [Implementation Roadmap](08-implementation-roadmap.md) | Phased delivery |

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Test Coverage | 85%+ |
| PHPStan Level | 6 |
| Interface Compliance | 100% BuyableInterface, InventoryableInterface |
| Variant Combinations | Unlimited |
| Category Depth | Unlimited nesting |
| Media Integration | Spatie Media Library |

---

## Dependencies

### Required
| Package | Purpose |
|---------|---------|
| `aiarmada/commerce-support` | Shared interfaces and contracts |
| `spatie/laravel-medialibrary` | Media management |
| `akaunting/laravel-money` | Price handling |

### Optional (Auto-Integration)
| Package | Integration When Present |
|---------|--------------------------|
| `aiarmada/inventory` | Stock tracking per variant |
| `aiarmada/cart` | BuyableInterface implementation |
| `aiarmada/pricing` | Advanced pricing rules |
| `aiarmada/tax` | Tax class assignment |
| `aiarmada/affiliates` | Commission configuration |
| `aiarmada/cashier` | Subscription product sync |

---

## Navigation

**Next:** [02-product-architecture.md](02-product-architecture.md)
