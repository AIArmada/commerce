# Tax Vision Progress

> **Package:** `aiarmada/tax`  
> **Last Updated:** December 2025  
> **Status:** ✅ Complete (Core Features)

---

## Package Hierarchy

```
┌─────────────────────────────────────────────────────────────────┐
│                       TAX PACKAGE POSITION                       │
├─────────────────────────────────────────────────────────────────┤
│   ┌─────────────────────────────────────────────────────────┐   │
│   │              aiarmada/commerce-support                   │   │
│   │         (Shared Interfaces & Contracts)                  │   │
│   └─────────────────────────────────────────────────────────┘   │
│                              │                                   │
│       ┌──────────────────────┼──────────────────────┐           │
│       ▼                      ▼                      ▼           │
│   ┌────────────┐      ┌────────────┐      ┌────────────┐        │
│   │  products  │      │  pricing   │      │    tax     │◄──THIS │
│   └────────────┘      └────────────┘      └────────────┘        │
│         │                    │                    │              │
│         └────────────────────┼────────────────────┘              │
│                              ▼                                   │
│                       ┌────────────┐                             │
│                       │   orders   │                             │
│                       └────────────┘                             │
└─────────────────────────────────────────────────────────────────┘
```

---

## Implementation Status

| Phase | Status | Progress |
|-------|--------|----------|
| Phase 1: Core Models | 🟢 **Complete** | 100% |
| Phase 2: Calculation Engine | 🟢 **Complete** | 100% |
| Phase 3: Exemptions | 🟢 **Complete** | 100% |
| Phase 4: Settings | 🟢 **Complete** | 100% |

---

## Phase 1: Core Models ✅

### TaxZone Model
- [x] UUID-based model with `HasUuids` trait
- [x] Soft deletes support
- [x] `HasOwner` trait for multitenancy
- [x] Geographic matching via `matchesAddress()`:
  - Country matching
  - State matching
  - Postcode ranges and wildcards
- [x] Priority ordering for zone matching
- [x] Default zone flag (`is_default`)
- [x] Active/inactive toggle (`is_active`)
- [x] Spatie activity log integration (`LogsActivity` trait)
- [x] `getTable()` from config (no hardcoded table names)
- [x] Scopes: `active()`, `forOwner()`

### TaxRate Model
- [x] UUID-based model with `HasUuids` trait
- [x] Rate stored as basis points (600 = 6%)
- [x] Tax class association (`tax_class` string)
- [x] Zone relationship (`zone_id` foreign key)
- [x] Compound tax support (`is_compound`)
- [x] Priority for compound ordering
- [x] Helper methods:
  - `getRateDecimal()` - Returns rate as decimal (0.06)
  - `getRatePercentage()` - Returns rate as percentage (6.0)
  - `calculateTax(int $amount)` - Calculate tax for amount
  - `extractTax(int $inclusiveAmount)` - Extract tax from inclusive price
- [x] Scopes: `active()`, `forZone()`, `forClass()`

### TaxClass Model
- [x] UUID-based model with `HasUuids` trait
- [x] Standard, Reduced, Zero, Exempt class support
- [x] Default class flag (`is_default`)
- [x] Display ordering (`position`)
- [x] Active/inactive toggle (`is_active`)
- [x] `HasOwner` trait for multitenancy
- [x] Scopes: `active()`, `default()`, `ordered()`

### TaxExemption Model
- [x] UUID-based model with `HasUuids` trait
- [x] Polymorphic `exemptable` relationship (Customer, User, etc.)
- [x] Zone-specific exemptions (`tax_zone_id` foreign key)
- [x] Certificate tracking (`certificate_number`)
- [x] Document upload support (`document_path`)
- [x] Date range (`starts_at`, `expires_at`)
- [x] Approval workflow (`status`: pending, approved, rejected)
- [x] Reason/notes fields
- [x] Helper methods:
  - `approve()` / `reject()` - Workflow actions
  - `isActive()` - Check if approved and not expired
  - `isExpired()` - Check if past expiration date
  - `appliesToZone(?string $zoneId)` - Check zone applicability
- [x] Scopes: `active()`, `forZone($zoneId)`, `expiring($days)`

---

## Phase 2: Calculation Engine ✅

### TaxCalculator Service
- [x] Zone resolution from address data
- [x] Exemption checking with zone scope
- [x] Rate lookup by class and zone
- [x] Tax calculation methods:
  - `calculateTax(int $amount, string $taxClass, array $address, ?Model $entity)`
  - `calculateTaxes(array $items, array $address, ?Model $entity)`
  - `extractTax(int $inclusiveAmount, string $taxClass, array $address)`
- [x] Compound tax calculation (multiple rates applied sequentially)
- [x] Shipping tax calculation

### DTOs
- [x] `TaxResult` DTO with:
  - `amount` - Tax amount in minor units
  - `rate` - Rate as decimal
  - `zoneName` - Name of matched zone
  - `zoneId` - ID of matched zone
  - `rateName` - Name of tax rate
  - `isExempt` - Whether entity is exempt
  - `exemptionReason` - Reason if exempt

### Exceptions
- [x] `TaxZoneNotFoundException` - Thrown when no zone matches address

---

## Phase 3: Exemptions ✅

### TaxExemption Features
- [x] Polymorphic `exemptable` (attach to any model)
- [x] Zone-specific exemptions (via `taxZone()` relationship)
- [x] Certificate number tracking
- [x] Document path for uploaded certificates
- [x] Date range validity (`starts_at` to `expires_at`)
- [x] Approval workflow with status transitions
- [x] `forZone()` scope for zone-specific queries
- [x] `expiring($days)` scope for expiration alerts

---

## Phase 4: Settings ✅

### TaxSettings (Spatie Laravel Settings)
- [x] `TaxSettings` class extending `Spatie\LaravelSettings\Settings`
- [x] Runtime-modifiable configuration:
  - `enabled` - Global tax toggle
  - `defaultTaxRate` - Default rate percentage
  - `defaultTaxName` - Tax label (SST, GST, VAT)
  - `pricesIncludeTax` - Inclusive pricing flag
  - `taxBasedOnShippingAddress` - Address for calculation
  - `digitalGoodsTaxable` - Digital product taxation
  - `shippingTaxable` - Shipping taxation
  - `taxIdLabel` - Customer tax ID label
  - `validateTaxIds` - Tax ID validation flag
  - `requireExemptionCertificate` - B2B certificate requirement
- [x] Settings methods:
  - `calculateTax(int $subtotal)` - Quick tax calculation
  - `extractTax(int $inclusivePrice)` - Quick tax extraction

---

## Database Schema

### Tables Created
| Table | Purpose |
|-------|---------|
| `tax_zones` | Geographic tax zones |
| `tax_classes` | Product tax categories |
| `tax_rates` | Tax rates per zone/class |
| `tax_exemptions` | Customer exemptions |

### Migration Files
- `2024_01_01_000001_create_tax_zones_table.php`
- `2024_01_01_000002_create_tax_classes_table.php`
- `2024_01_01_000003_create_tax_rates_table.php`
- `2024_01_01_000004_create_tax_exemptions_table.php`

---

## Files Structure

```
packages/tax/
├── composer.json
├── config/
│   └── tax.php
├── database/
│   ├── migrations/
│   │   ├── 2024_01_01_000001_create_tax_zones_table.php
│   │   ├── 2024_01_01_000002_create_tax_classes_table.php
│   │   ├── 2024_01_01_000003_create_tax_rates_table.php
│   │   └── 2024_01_01_000004_create_tax_exemptions_table.php
│   └── settings/
│       └── 2024_01_01_000005_create_tax_settings.php
├── docs/
│   └── vision/
│       ├── 01-executive-summary.md
│       ├── 02-tax-zones.md
│       ├── 03-tax-rates.md
│       ├── 04-tax-classes.md
│       ├── 05-database-schema.md
│       ├── 06-implementation-roadmap.md
│       └── PROGRESS.md
└── src/
    ├── DTOs/
    │   └── TaxResult.php
    ├── Exceptions/
    │   └── TaxZoneNotFoundException.php
    ├── Models/
    │   ├── TaxClass.php
    │   ├── TaxExemption.php
    │   ├── TaxRate.php
    │   └── TaxZone.php
    ├── Services/
    │   └── TaxCalculator.php
    ├── Settings/
    │   └── TaxSettings.php
    └── TaxServiceProvider.php
```

---

## Malaysia-Specific Features

### SST Support
- [x] 6% Sales & Service Tax rate configuration
- [x] Service and sales tax distinction
- [x] Exempt categories configuration

### Default Configuration
```php
'malaysia' => [
    'sst_rate' => 6,
    'exempt_categories' => ['basic_necessities', 'agricultural', 'medical'],
],
```

---

## Vision Documents

| Document | Status |
|----------|--------|
| [01-executive-summary.md](01-executive-summary.md) | ✅ Implemented |
| [02-tax-zones.md](02-tax-zones.md) | ✅ Implemented |
| [03-tax-rates.md](03-tax-rates.md) | ✅ Implemented |
| [04-tax-classes.md](04-tax-classes.md) | ✅ Implemented |
| [05-database-schema.md](05-database-schema.md) | ✅ Implemented |
| [06-implementation-roadmap.md](06-implementation-roadmap.md) | ✅ Implemented |

---

## Verification Results

| Check | Result |
|-------|--------|
| PHPStan Level 6 | ✅ Pass |
| Tests | ✅ 14 tests pass |
| All models functional | ✅ |
| TaxCalculator service | ✅ |
| TaxSettings integration | ✅ |

---

## Integration Points

### Spatie Packages Used
| Package | Purpose | Status |
|---------|---------|--------|
| `spatie/laravel-activitylog` | Tax rate change audit | ✅ Implemented |
| `spatie/laravel-settings` | Runtime tax configuration | ✅ Implemented |

### Cross-Package Integration
| Package | Integration | Status |
|---------|-------------|--------|
| `products` | Tax class assignment | 🔮 Future |
| `orders` | Tax calculation at checkout | 🔮 Future |
| `customers` | Tax exemption management | 🔮 Future |

---

## Legend

| Symbol | Meaning |
|--------|---------|
| 🔴 | Not Started |
| 🟡 | In Progress |
| 🟢 | Completed |
| 🔮 | Future/Planned |
