# Executive Summary

> **Document:** 01 of 07  
> **Package:** `aiarmada/customers` + `aiarmada/filament-customers`  
> **Status:** Vision  
> **Last Updated:** December 2025

---

## Vision Statement

Establish the **Customers Package** as the comprehensive customer relationship management (CRM) layer of the AIArmada Commerce ecosystem—managing customer profiles, address books, wishlists, segments, and providing a unified view of customer interactions across all commerce touchpoints.

---

## Strategic Position in Commerce Ecosystem

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     AIARMADA COMMERCE ECOSYSTEM                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────────────────────────────────────────────────────────┐   │
│   │                    Laravel User Model (Auth)                     │   │
│   └─────────────────────────────────────────────────────────────────┘   │
│                                    │                                     │
│                                    ▼                                     │
│   ┌─────────────────────────────────────────────────────────────────┐   │
│   │                  aiarmada/customers ◄── THIS PACKAGE             │   │
│   │              (CRM & Profile Management)                          │   │
│   └─────────────────────────────────────────────────────────────────┘   │
│                                    │                                     │
│       ┌────────────────────────────┼────────────────────────────┐       │
│       ▼                            ▼                            ▼       │
│   ┌────────────┐            ┌────────────┐            ┌────────────┐    │
│   │   orders   │            │   cashier  │            │  products  │    │
│   │ (History)  │            │ (Billing)  │            │ (Wishlist) │    │
│   └────────────┘            └────────────┘            └────────────┘    │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Core Design Principles

### 1. **User Extension, Not Replacement**
Customers extend the base Laravel User model via trait composition, not inheritance. This allows any User model to become a Customer.

### 2. **Profile Separation**
Customer commerce data is stored separately from authentication data, maintaining clean separation of concerns.

### 3. **Address Book Native**
Multiple addresses per customer with type classification (billing, shipping, default flags).

### 4. **Segment-Driven**
Customers belong to segments (Retail, Wholesale, VIP) that drive pricing and feature access.

### 5. **GDPR-Ready**
Built-in support for data export, anonymization, and deletion requests.

---

## Package Responsibilities

| Responsibility | Description |
|----------------|-------------|
| **Customer Profiles** | Extended commerce data beyond User model |
| **Address Book** | Multiple addresses per customer |
| **Wishlists** | Save products for later |
| **Customer Segments** | Groups for pricing/feature targeting |
| **Customer Groups** | B2B company/team organization |
| **Customer Wallet** | Store credit and balance tracking |
| **Activity Timeline** | Unified view of customer interactions |
| **Data Export** | GDPR personal data export |
| **Data Deletion** | Right to be forgotten implementation |

---

## Package Non-Responsibilities

| Delegated To | Responsibility |
|--------------|----------------|
| Laravel Auth | Authentication, password reset |
| `orders` | Order history |
| `cashier` | Subscription management, payment methods |
| `affiliates` | Affiliate relationships |
| `vouchers` | Personal voucher codes |

---

## Core Models

### Customer Model
```php
namespace AIArmada\Customers\Models;

class Customer extends Model
{
    public function user(): BelongsTo;
    public function addresses(): HasMany;
    public function wishlists(): HasMany;
    public function segments(): BelongsToMany;
    public function groups(): BelongsToMany;
    public function wallet(): HasOne;
    
    // Computed from Orders package when present
    public function getTotalSpent(): Money;
    public function getOrderCount(): int;
    public function getAverageOrderValue(): Money;
    public function getLastOrderDate(): ?Carbon;
}
```

### Address Model
```php
namespace AIArmada\Customers\Models;

class Address extends Model
{
    protected $fillable = [
        'customer_id',
        'type',           // AddressType::Billing, Shipping
        'label',          // "Home", "Office"
        'first_name',
        'last_name',
        'company',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country_code',
        'phone',
        'is_default_billing',
        'is_default_shipping',
    ];
    
    public function customer(): BelongsTo;
}
```

### CustomerSegment Model
```php
namespace AIArmada\Customers\Models;

class CustomerSegment extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',           // SegmentType::Manual, Automatic
        'conditions',     // JSON rules for automatic
        'priority',       // For pricing rule precedence
    ];
    
    public function customers(): BelongsToMany;
}
```

---

## Segment-Based Features

| Segment | Features |
|---------|----------|
| **Retail** | Standard pricing, public promotions |
| **Wholesale** | Volume pricing, net terms |
| **VIP** | Exclusive discounts, early access |
| **Tax Exempt** | Tax-free purchasing |
| **Restricted** | Limited product access |

---

## Integration Points

### With Pricing Package
```php
// Customer segment affects price calculation
$engine->calculate($product, $customer);
// → Checks customer segments for applicable price rules
```

### With Orders Package
```php
// Customer order history
$customer->orders(); // Via commerce-support OrderableInterface
```

### With Cashier Package
```php
// Customer payment methods and subscriptions
$customer->user->paymentMethods();
$customer->user->subscriptions();
```

---

## Vision Documents

| # | Document | Focus |
|---|----------|-------|
| 01 | Executive Summary | This document |
| 02 | [Customer Profiles](02-customer-profiles.md) | Core data model |
| 03 | [Address Management](03-address-management.md) | Address book features |
| 04 | [Segments & Groups](04-segments-groups.md) | Customer classification |
| 05 | [Wishlist System](05-wishlists.md) | Save for later |
| 06 | [GDPR Compliance](06-gdpr.md) | Privacy features |
| 07 | [Implementation Roadmap](07-implementation-roadmap.md) | Phased delivery |

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Test Coverage | 85%+ |
| PHPStan Level | 6 |
| Address Types | Unlimited |
| Segments | Rule-based automatic |
| GDPR Export | Complete data |

---

## Dependencies

### Required
| Package | Purpose |
|---------|---------|
| `aiarmada/commerce-support` | Shared interfaces |

### Optional (Auto-Integration)
| Package | Integration When Present |
|---------|--------------------------|
| `aiarmada/orders` | Order history display |
| `aiarmada/cashier` | Payment method access |
| `aiarmada/products` | Wishlist products |
| `aiarmada/pricing` | Segment-based pricing |
| `aiarmada/affiliates` | Referral attribution |

---

## Navigation

**Next:** [02-customer-profiles.md](02-customer-profiles.md)
