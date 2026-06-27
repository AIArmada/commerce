# Audit: `filament-customers` (AIArmada\FilamentCustomers)

**Status:** Ready

---

## Findings

### Low
1. **Config keys consumed but not declared** — `features.merge_customers`, `features.segment_rebuild`, `features.address_validation` read in Plugin but absent from config file. Fall back to defaults.
2. **Cross-package config coupling** — Pages read `config('customers.features.owner.enabled')` directly instead of through `OwnerUiScope` (which handles the disabled case internally).

## Summary

**2 resources** (Customer, Segment), **8 pages**, **3 standalone pages** (MergeCustomers, SegmentRebuild, AddressValidation), **2 relation managers**, **2 widgets**. Navigation clean. Consistent `OwnerUiScope::apply(includeGlobal: false)` on all query paths. 14 tests, 6 docs.

**Verdict:** Ready.
