# Executive Summary

> **Document:** 01 of 07  
> **Package:** `aiarmada/tax` + `aiarmada/filament-tax`  
> **Status:** Vision  
> **Last Updated:** December 2025

---

## Vision Statement

Establish the **Tax Package** as the comprehensive tax calculation and compliance layer of the AIArmada Commerce ecosystem—supporting global tax jurisdictions, multiple tax types (VAT, GST, Sales Tax), tax exemptions, and automated rate lookups while maintaining compliance-ready audit trails.

---

## Strategic Position in Commerce Ecosystem

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     AIARMADA COMMERCE ECOSYSTEM                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                     │
│   │  PRODUCTS   │  │   PRICING   │  │  SHIPPING   │                     │
│   │ (Tax Class) │  │ (Pre-Tax)   │  │ (Taxable?)  │                     │
│   └──────┬──────┘  └──────┬──────┘  └──────┬──────┘                     │
│          │                │                │                             │
│          └────────────────┴────────────────┘                             │
│                           │                                              │
│                           ▼                                              │
│                   ┌───────────────┐                                      │
│                   │      TAX      │ ◄── THIS PACKAGE                     │
│                   │ (Calculation) │                                      │
│                   └───────┬───────┘                                      │
│                           │                                              │
│           ┌───────────────┼───────────────┐                              │
│           ▼               ▼               ▼                              │
│   ┌───────────────┐ ┌────────────┐ ┌────────────┐                        │
│   │     CART      │ │   ORDERS   │ │  INVOICES  │                        │
│   │  (Display)    │ │ (Snapshot) │ │  (Report)  │                        │
│   └───────────────┘ └────────────┘ └────────────┘                        │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Core Design Principles

### 1. **Zone-Based Calculation**
Tax rates are determined by geographic zones (country, state, postal code) rather than simple country codes.

### 2. **Product Tax Classes**
Products are assigned tax classes (Standard, Reduced, Zero-Rated) that determine applicable rates.

### 3. **Address-Driven**
Tax is calculated based on the destination address (for shipping) or origin address (for digital goods / VAT MOSS).

### 4. **Snapshot on Order**
Tax amounts are snapshotted at order time. Future rate changes don't affect historical orders.

### 5. **Integration-Ready**
Designed to integrate with external tax services (TaxJar, Avalara) while maintaining a standalone fallback.

---

## Package Responsibilities

| Responsibility | Description |
|----------------|-------------|
| **Tax Zones** | Geographic regions with tax rules |
| **Tax Rates** | Rate percentages per zone/class |
| **Tax Classes** | Product categorization for tax purposes |
| **Tax Calculation** | Central calculation engine |
| **Tax Exemptions** | Customer/business exemption handling |
| **Tax Reports** | Compliance reporting |
| **Rate Lookup** | Automatic rate determination |

---

## Package Non-Responsibilities

| Delegated To | Responsibility |
|--------------|----------------|
| `products` | Tax class assignment |
| `customers` | Tax exemption status |
| `cart` | Tax display |
| `orders` | Tax snapshotting |
| External Services | Real-time rate updates (optional) |

---

## Tax Calculation Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        TAX CALCULATION FLOW                              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌──────────────┐                                                       │
│   │ Cart Subtotal│  RM 100.00 (post-pricing, pre-discount)              │
│   └──────┬───────┘                                                       │
│          │                                                               │
│          ▼                                                               │
│   ┌──────────────┐                                                       │
│   │ Apply Voucher│  -RM 10.00 discount                                  │
│   └──────┬───────┘                                                       │
│          │                                                               │
│          ▼                                                               │
│   ┌──────────────┐                                                       │
│   │ Taxable Base │  RM 90.00                                            │
│   └──────┬───────┘                                                       │
│          │                                                               │
│          ▼                                                               │
│   ┌──────────────┐                                                       │
│   │ Determine    │  Destination: Kuala Lumpur, Malaysia                 │
│   │ Tax Zone     │  Zone: Malaysia                                      │
│   └──────┬───────┘                                                       │
│          │                                                               │
│          ▼                                                               │
│   ┌──────────────┐                                                       │
│   │ Lookup Rate  │  Product: "T-Shirt" (Standard Class)                 │
│   │ by Class     │  Rate: 6% (Malaysian SST)                            │
│   └──────┬───────┘                                                       │
│          │                                                               │
│          ▼                                                               │
│   ┌──────────────┐                                                       │
│   │ Tax Amount   │  RM 5.40                                             │
│   └──────┬───────┘                                                       │
│          │                                                               │
│          ▼                                                               │
│   ┌──────────────┐                                                       │
│   │ Grand Total  │  RM 95.40                                            │
│   └──────────────┘                                                       │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Core Models

### TaxZone Model
```php
namespace AIArmada\Tax\Models;

class TaxZone extends Model
{
    protected $fillable = [
        'name',           // "Malaysia", "EU VAT Zone"
        'slug',
        'description',
        'zone_type',      // ZoneType::Country, State, PostalRange
        'countries',      // ["MY"], or ["DE", "FR", "IT"...]
        'states',         // ["CA", "NY"] for US states
        'postal_from',
        'postal_to',
    ];
    
    public function rates(): HasMany;
    
    public function matchesAddress(Address $address): bool;
}
```

### TaxRate Model
```php
namespace AIArmada\Tax\Models;

class TaxRate extends Model
{
    protected $fillable = [
        'tax_zone_id',
        'tax_class_id',
        'name',           // "SST", "VAT", "GST"
        'rate',           // 6.00 (percentage)
        'is_compound',    // Applies after other taxes
        'priority',       // Order of application
        'is_shipping',    // Applies to shipping
        'starts_at',
        'ends_at',
    ];
    
    public function zone(): BelongsTo;
    public function taxClass(): BelongsTo;
}
```

### TaxClass Model
```php
namespace AIArmada\Tax\Models;

class TaxClass extends Model
{
    protected $fillable = [
        'name',           // "Standard", "Reduced", "Zero-Rated"
        'slug',
        'description',
        'is_default',
    ];
    
    public function rates(): HasMany;
}
```

---

## Tax Engine API

```php
namespace AIArmada\Tax;

class TaxEngine
{
    public function calculate(
        TaxableInterface $item,
        Address $address,
        ?Money $amount = null
    ): TaxResult;
    
    public function calculateCart(
        Cart $cart,
        Address $shippingAddress,
        ?Address $billingAddress = null
    ): CartTaxResult;
    
    public function getRateFor(
        TaxableInterface $item,
        Address $address
    ): float;
    
    public function getZoneFor(Address $address): ?TaxZone;
    
    public function isExempt(
        Customer $customer,
        ?TaxZone $zone = null
    ): bool;
}
```

---

## Tax Types Supported

| Tax Type | Region | Implementation |
|----------|--------|----------------|
| **VAT** | EU, UK | Inclusive pricing, VAT MOSS |
| **GST** | Australia, NZ, India | Similar to VAT |
| **SST** | Malaysia | Service and Sales Tax |
| **Sales Tax** | US | Nexus-based, destination |
| **Custom** | Any | Configurable rates |

---

## Vision Documents

| # | Document | Focus |
|---|----------|-------|
| 01 | Executive Summary | This document |
| 02 | [Tax Zones](02-tax-zones.md) | Geographic regions |
| 03 | [Tax Rates](03-tax-rates.md) | Rate configuration |
| 04 | [Tax Classes](04-tax-classes.md) | Product classification |
| 05 | [Exemptions](05-exemptions.md) | B2B, non-profit exemptions |
| 06 | [Database Schema](06-database-schema.md) | Tables, relationships |
| 07 | [Implementation Roadmap](07-implementation-roadmap.md) | Phased delivery |

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Test Coverage | 90%+ |
| PHPStan Level | 6 |
| Zone Matching | 100% accuracy |
| Calculation Speed | <5ms |
| Tax Types | VAT, GST, Sales Tax |

---

## Dependencies

### Required
| Package | Purpose |
|---------|---------|
| `aiarmada/commerce-support` | Shared interfaces |
| `akaunting/laravel-money` | Amount handling |

### Optional (Auto-Integration)
| Package | Integration When Present |
|---------|--------------------------|
| `aiarmada/products` | Tax class assignment |
| `aiarmada/customers` | Tax exemption status |
| `aiarmada/cart` | Tax calculation in cart |
| `aiarmada/orders` | Tax snapshotting |
| `aiarmada/shipping` | Shipping tax |

---

## Navigation

**Next:** [02-tax-zones.md](02-tax-zones.md)
