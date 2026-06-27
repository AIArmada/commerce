# Audit: `filament-pricing` (AIArmada\FilamentPricing)

**Status:** Conditionally ready

---

## Findings

### Medium
1. **Zero tests** — No test files exist.

### Low
2. **4 doc examples show deprecated `$navigationGroup`** — `05-resources.md` and `06-pages-widgets.md` show the static property instead of config-driven `getNavigationGroup()`.
3. **`TiersRelationManager` uses bespoke scoping** — Uses `method_exists($model, 'scopeForOwner')` instead of `OwnerQuery::applyToEloquentBuilder()` used everywhere else.

## Summary

2 resources (PriceList, Promotion fallback), 2 standalone pages (ManagePricingSettings, PriceSimulator), 2 relation managers, 1 widget. Navigation clean. Owner scoping correct on both resources. 8 docs (exceeds minimum).

**Verdict:** Conditionally ready. Add minimum test coverage. Fix doc examples.
