# Customers Vision Progress

> **Package:** `aiarmada/customers` + `aiarmada/filament-customers`  
> **Last Updated:** December 2025  
> **Status:** Vision Complete, Implementation Pending

---

## Package Hierarchy

```
┌─────────────────────────────────────────────────────────────────┐
│                   CUSTOMERS PACKAGE POSITION                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│   ┌─────────────────────────────────────────────────────────┐   │
│   │               Laravel User Model (Auth)                  │   │
│   └─────────────────────────────────────────────────────────┘   │
│                              │                                   │
│                              ▼                                   │
│   ┌─────────────────────────────────────────────────────────┐   │
│   │                aiarmada/customers ◄── THIS PACKAGE       │   │
│   │             (CRM & Profile Management)                   │   │
│   └─────────────────────────────────────────────────────────┘   │
│                              │                                   │
│       ┌──────────────────────┼──────────────────────┐           │
│       ▼                      ▼                      ▼           │
│   ┌────────────┐      ┌────────────┐      ┌────────────┐        │
│   │   orders   │      │  pricing   │      │  products  │        │
│   │ (History)  │      │ (Segment)  │      │ (Wishlist) │        │
│   └────────────┘      └────────────┘      └────────────┘        │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Implementation Status

| Phase | Status | Progress |
|-------|--------|----------|
| Phase 1: Core Models | 🔴 Not Started | 0% |
| Phase 2: Address Book | 🔴 Not Started | 0% |
| Phase 3: Segments & Groups | 🔴 Not Started | 0% |
| Phase 4: Wishlists | 🔴 Not Started | 0% |
| Phase 5: Filament Admin | 🔴 Not Started | 0% |

---

## Phase 1: Core Models

### Customer Model
- [ ] `Customer` model extending User commerce data
- [ ] `HasCustomerProfile` trait for User model
- [ ] Customer wallet / store credit
- [ ] Activity timeline

### Base Infrastructure
- [ ] `CustomersServiceProvider`
- [ ] Configuration file (`config/customers.php`)
- [ ] Database migrations
- [ ] Factories and seeders

---

## Phase 2: Address Book

### Address Model
- [ ] `Address` model with full address fields
- [ ] Address types (billing, shipping)
- [ ] Default address flags
- [ ] Address validation integration

### Features
- [ ] Multiple addresses per customer
- [ ] Address labels ("Home", "Office")
- [ ] Auto-complete with Google/HERE
- [ ] Address verification

---

## Phase 3: Segments & Groups

### Customer Segments
- [ ] `CustomerSegment` model
- [ ] Manual vs automatic segment assignment
- [ ] Condition-based rules
- [ ] Priority ordering for pricing

### Customer Groups
- [ ] `CustomerGroup` model (B2B teams)
- [ ] Group admins and members
- [ ] Shared payment methods
- [ ] Group spending limits

---

## Phase 4: Wishlists

### Wishlist Model
- [ ] `Wishlist` model (multiple per customer)
- [ ] `WishlistItem` model
- [ ] Public vs private wishlists
- [ ] Share via link
- [ ] "Add all to cart" functionality

---

## Phase 5: Filament Admin

### Resources
- [ ] `CustomerResource` with comprehensive views
- [ ] `AddressResource` for address management
- [ ] `SegmentResource` with rule builder

### Pages
- [ ] Customer dashboard with analytics
- [ ] Customer 360 view (orders, payments, activity)
- [ ] Segment management

### Widgets
- [ ] Customer stats (new, active, LTV)
- [ ] Segment distribution
- [ ] Top customers

---

## Vision Documents

| Document | Status |
|----------|--------|
| [01-executive-summary.md](01-executive-summary.md) | ✅ Complete |
| 02-customer-profiles.md | ⏳ Pending |
| 03-address-management.md | ⏳ Pending |
| 04-segments-groups.md | ⏳ Pending |
| 05-wishlists.md | ⏳ Pending |
| 06-gdpr.md | ⏳ Pending |
| 07-implementation-roadmap.md | ⏳ Pending |

---

## Dependencies

### Required
| Package | Purpose |
|---------|---------|
| `aiarmada/commerce-support` | Shared interfaces |

### Optional (Auto-Integration)
| Package | Integration |
|---------|-------------|
| `aiarmada/orders` | Order history |
| `aiarmada/cashier` | Payment methods |
| `aiarmada/products` | Wishlist products |
| `aiarmada/pricing` | Segment pricing |

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Test Coverage | 85%+ |
| PHPStan Level | 6 |
| Address Types | Unlimited |
| Segments | Rule-based |
| GDPR Compliant | Yes |

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
- Package positioned as CRM layer
- GDPR compliance prioritized
- 5-phase implementation roadmap established
