# Tax Vision Progress

> **Package:** `aiarmada/tax` + `aiarmada/filament-tax`  
> **Last Updated:** December 2025  
> **Status:** Vision Complete, Implementation Pending

---

## Package Hierarchy

```
┌─────────────────────────────────────────────────────────────────┐
│                      TAX PACKAGE POSITION                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│   ┌────────────┐   ┌────────────┐   ┌────────────┐              │
│   │  products  │   │  pricing   │   │  shipping  │              │
│   │ (Tax Class)│   │ (Pre-Tax)  │   │ (Taxable?) │              │
│   └─────┬──────┘   └─────┬──────┘   └─────┬──────┘              │
│         └────────────────┴────────────────┘                      │
│                          │                                       │
│                          ▼                                       │
│   ┌─────────────────────────────────────────────────────────┐   │
│   │                  aiarmada/tax ◄── THIS PACKAGE           │   │
│   │                (Tax Calculation)                         │   │
│   └─────────────────────────────────────────────────────────┘   │
│                              │                                   │
│       ┌──────────────────────┼──────────────────────┐           │
│       ▼                      ▼                      ▼           │
│   ┌────────────┐      ┌────────────┐      ┌────────────┐        │
│   │    cart    │      │   orders   │      │  invoices  │        │
│   │ (Display)  │      │ (Snapshot) │      │  (Report)  │        │
│   └────────────┘      └────────────┘      └────────────┘        │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Implementation Status

| Phase | Status | Progress |
|-------|--------|----------|
| Phase 1: Core Engine | 🔴 Not Started | 0% |
| Phase 2: Tax Zones | 🔴 Not Started | 0% |
| Phase 3: Tax Classes | 🔴 Not Started | 0% |
| Phase 4: Exemptions | 🔴 Not Started | 0% |
| Phase 5: Filament Admin | 🔴 Not Started | 0% |

---

## Phase 1: Core Engine

### Tax Engine
- [ ] `TaxEngine` service with calculate API
- [ ] `TaxResult` value object
- [ ] Address-based zone matching
- [ ] Multi-rate calculation (compound taxes)

### Base Infrastructure
- [ ] `TaxServiceProvider`
- [ ] Configuration file (`config/tax.php`)
- [ ] Tax caching strategy
- [ ] Calculation logging

---

## Phase 2: Tax Zones

### Tax Zone Model
- [ ] `TaxZone` model with geographic matching
- [ ] Zone types (Country, State, Postal Range)
- [ ] Zone priority ordering
- [ ] Fallback zone support

### Zone Matching
- [ ] Address-to-zone resolution
- [ ] Postal code range matching
- [ ] State/province matching
- [ ] Country fallback

---

## Phase 3: Tax Classes

### Tax Class Model
- [ ] `TaxClass` model (Standard, Reduced, Zero)
- [ ] Default class assignment
- [ ] Product association

### Tax Rate Model
- [ ] `TaxRate` model per zone/class
- [ ] Compound tax support
- [ ] Time-based rate changes
- [ ] Shipping tax flag

---

## Phase 4: Exemptions

### Exemption Model
- [ ] `TaxExemption` model
- [ ] Customer exemption certificates
- [ ] B2B exemptions
- [ ] Non-profit exemptions

### Validation
- [ ] Exemption certificate validation
- [ ] Expiry tracking
- [ ] Renewal reminders

---

## Phase 5: Filament Admin

### Resources
- [ ] `TaxZoneResource`
- [ ] `TaxRateResource`
- [ ] `TaxClassResource`
- [ ] `TaxExemptionResource`

### Pages
- [ ] Tax dashboard
- [ ] Rate configuration
- [ ] Exemption management

### Widgets
- [ ] Tax collection summary
- [ ] Zone coverage map
- [ ] Recent exemptions

---

## Vision Documents

| Document | Status |
|----------|--------|
| [01-executive-summary.md](01-executive-summary.md) | ✅ Complete |
| 02-tax-zones.md | ⏳ Pending |
| 03-tax-rates.md | ⏳ Pending |
| 04-tax-classes.md | ⏳ Pending |
| 05-exemptions.md | ⏳ Pending |
| 06-database-schema.md | ⏳ Pending |
| 07-implementation-roadmap.md | ⏳ Pending |

---

## Dependencies

### Required
| Package | Purpose |
|---------|---------|
| `aiarmada/commerce-support` | Shared interfaces |
| `akaunting/laravel-money` | Amount handling |

### Optional (Auto-Integration)
| Package | Integration |
|---------|-------------|
| `aiarmada/products` | Tax class assignment |
| `aiarmada/customers` | Tax exemption status |
| `aiarmada/cart` | Tax calculation |
| `aiarmada/orders` | Tax snapshotting |
| `aiarmada/shipping` | Shipping tax |

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Test Coverage | 90%+ |
| PHPStan Level | 6 |
| Zone Matching | 100% |
| Calculation Speed | <5ms |
| Tax Types | VAT, GST, SST, Sales |

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
- Package positioned as compliance-ready tax engine
- Zone-based calculation architecture defined
- 5-phase implementation roadmap established
