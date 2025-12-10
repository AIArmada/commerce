# Pricing Vision Progress

> **Package:** `aiarmada/pricing` + `aiarmada/filament-pricing`  
> **Last Updated:** December 2025  
> **Status:** Vision Complete, Implementation Pending

---

## Package Hierarchy

```
┌─────────────────────────────────────────────────────────────────┐
│                    PRICING PACKAGE POSITION                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│   ┌─────────────────────────────────────────────────────────┐   │
│   │                  aiarmada/products                       │   │
│   │                   (Base Prices)                          │   │
│   └─────────────────────────────────────────────────────────┘   │
│                              │                                   │
│                              ▼                                   │
│   ┌─────────────────────────────────────────────────────────┐   │
│   │                  aiarmada/pricing ◄── THIS PACKAGE       │   │
│   │                (Dynamic Price Rules)                     │   │
│   └─────────────────────────────────────────────────────────┘   │
│                              │                                   │
│       ┌──────────────────────┼──────────────────────┐           │
│       ▼                      ▼                      ▼           │
│   ┌────────────┐      ┌────────────┐      ┌────────────┐        │
│   │ customers  │      │    cart    │      │   orders   │        │
│   │ (Segment)  │      │ (Applied)  │      │ (Snapshot) │        │
│   └────────────┘      └────────────┘      └────────────┘        │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Implementation Status

| Phase | Status | Progress |
|-------|--------|----------|
| Phase 1: Core Engine | 🔴 Not Started | 0% |
| Phase 2: Price Lists | 🔴 Not Started | 0% |
| Phase 3: Tiered Pricing | 🔴 Not Started | 0% |
| Phase 4: Price Rules | 🔴 Not Started | 0% |
| Phase 5: Filament Admin | 🔴 Not Started | 0% |

---

## Phase 1: Core Engine

### Pricing Engine
- [ ] `PricingEngine` service with calculate API
- [ ] `PriceResult` value object
- [ ] `PriceExplanation` for debugging
- [ ] Context awareness (customer, quantity, time)

### Base Infrastructure
- [ ] `PricingServiceProvider`
- [ ] Configuration file (`config/pricing.php`)
- [ ] Price caching strategy
- [ ] Calculation logging

---

## Phase 2: Price Lists

### Price List Model
- [ ] `PriceList` model with priority ordering
- [ ] Customer segment association
- [ ] Time-based validity
- [ ] Default price list flag

### Price List Prices
- [ ] `PriceListPrice` model (product-specific overrides)
- [ ] Currency per price list
- [ ] Bulk import/export

---

## Phase 3: Tiered Pricing

### Tiered Price Model
- [ ] `TieredPrice` model with quantity breaks
- [ ] Per-product tier configuration
- [ ] Price list-specific tiers
- [ ] Tier display in cart

### Features
- [ ] "Buy X, get Y% off" display
- [ ] Next tier suggestion in cart
- [ ] Tier visualization widget

---

## Phase 4: Price Rules

### Price Rule Model
- [ ] `PriceRule` model with conditions
- [ ] Rule types (percentage, fixed, formula)
- [ ] Condition builder (JSON)
- [ ] Stackable vs exclusive rules

### Condition Types
- [ ] Customer segment
- [ ] Product category
- [ ] Quantity
- [ ] Date range
- [ ] Channel (web, app, POS)

---

## Phase 5: Filament Admin

### Resources
- [ ] `PriceListResource`
- [ ] `PriceRuleResource` with condition builder
- [ ] `TieredPriceResource`

### Pages
- [ ] Pricing dashboard
- [ ] Price calculator/simulator
- [ ] Bulk price update

### Widgets
- [ ] Active promotions
- [ ] Rule usage analytics
- [ ] Price change history

---

## Vision Documents

| Document | Status |
|----------|--------|
| [01-executive-summary.md](01-executive-summary.md) | ✅ Complete |
| [02-price-lists.md](02-price-lists.md) | ✅ Complete |
| [03-tiered-pricing.md](03-tiered-pricing.md) | ✅ Complete |
| [04-price-rules.md](04-price-rules.md) | ✅ Complete |
| [05-database-schema.md](05-database-schema.md) | ✅ Complete |
| [06-implementation-roadmap.md](06-implementation-roadmap.md) | ✅ Complete |

---

## Dependencies

### Required
| Package | Purpose |
|---------|---------|
| `aiarmada/commerce-support` | Shared interfaces |
| `akaunting/laravel-money` | Currency handling |

### Optional (Auto-Integration)
| Package | Integration |
|---------|-------------|
| `aiarmada/products` | Product price calculation |
| `aiarmada/customers` | Segment pricing |
| `aiarmada/cart` | Price application |
| `aiarmada/orders` | Price snapshotting |

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Test Coverage | 90%+ |
| PHPStan Level | 6 |
| Calculation Speed | <10ms |
| Rule Types | 3+ |
| Currency Support | Multi |

---

## Legend

| Symbol | Meaning |
|--------|---------|
| 🔴 | Not Started |
| 🟡 | In Progress |
| 🟢 | Completed |
| ⏳ | Pending |

---

## Notes

### December 2025
- Initial vision documentation created
- Package positioned as overlay pricing engine
- Emphasis on context-aware, auditable calculations
- 5-phase implementation roadmap established
