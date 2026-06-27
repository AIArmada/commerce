# Audit: `tax` (AIArmada\Tax)

**Status:** Ready with minor improvements

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Tax zones, rates, exemptions, and tax-calculation configuration.

**Surface:** domain

---

## Findings

### Medium
1. **Missing composer dependencies** — `composer.json` only declares `aiarmada/commerce-support`, but code imports `spatie/laravel-data`, `spatie/laravel-settings`, `spatie/laravel-model-states`, `owen-it/laravel-auditing`, and `akaunting/money`. These are transitively pulled by commerce-support but not explicitly declared.

### Low
2. **No exception hierarchy** — 1 custom exception (`TaxZoneNotFoundException`) across 3 actions, 3 events, 4 models. Missing `TaxCalculationException`.
3. **`number_format()` in `getFormattedRate()`** — Minor, display-only percentage formatting.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| `$guarded` usage | ✅ `$fillable` | All 4 models use explicit `$fillable` |
| Money storage | ✅ Integer basis points + cents | `rate` as basis points (600 = 6%), amounts in cents |
| Owner scoping | ✅ Full | All 4 models + services + resolvers |
| Migrations | ✅ Clean | No `constrained()`, no `cascadeOnDelete()` |
| Exception hierarchy | ⚠️ Partial | 1 class only (`TaxZoneNotFoundException`) |
| State machine | ✅ TaxExemption | 6 states via spatie/laravel-model-states |
| Actions | ✅ 3 classes | ApproveExemption, RejectExemption, RequestTaxExemption |
| Events | ✅ 3 events | TaxCalculated, TaxZoneResolved, TaxExemptionApplied |
| Contracts | ✅ 3 interfaces | TaxCalculatorInterface, TaxRateApplierInterface, TaxZoneResolverInterface |
| Tests | ✅ 19 files | Feature (cross-tenant) + Unit (models, services, data, settings) |
| Docs | ✅ 8 files | Full standard set + exemptions + models + multitenancy |

---

## Summary

Clean tax package: 4 models (TaxZone, TaxRate, TaxClass, TaxExemption), 2 enums, 3 actions, 3 events, 3 contracts, 1 custom exception, 6-state exemption state machine, 4 zone resolvers (Composite, ZoneId, Address, Default). Tax rates stored as integer basis points, amounts as integer cents. Full owner scoping on all models. 19 test files including cross-tenant isolation tests.

8 doc files covering all aspects.

**Verdict:** Ready with minor improvements. Fix `composer.json` to explicitly declare the Spatie packages and other libraries used at runtime.
