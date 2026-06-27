# Audit: `pricing` (AIArmada\Pricing)

**Status:** Ready

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Price lists, tiers, pricing settings, and price-resolution rules.

**Surface:** domain

---

## Findings

### Low
1. **No exception hierarchy** — 4 actions, 2 events, 0 custom exceptions. Uses `AuthorizationException` and `InvalidArgumentException` inline.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| `$guarded` usage | ✅ None | All 3 models use `$fillable` exclusively |
| Money storage | ✅ Integer minor units | `unsignedBigInteger` for amount, compare_amount, discount_value |
| Owner scoping | ✅ Full | All 3 models: `HasOwner` + `HasOwnerScopeConfig` + query+write enforcement |
| Migrations | ✅ Clean | No `constrained()`, no `cascadeOnDelete()`, no FK constraints |
| Cascades | ✅ Application-level | `PriceList::booted()` deleting cascades to prices/tiers |
| Contracts | ✅ 5 interfaces | `PriceCalculatorInterface`, `Priceable`, `CustomerPriceResolverInterface`, `SegmentPriceResolverInterface`, `TierResolverInterface` |
| Actions | ✅ 4 classes | ResolveBasePrice, ResolveTierPrice, ApplyPromotionalAdjustment, FormatPriceForDisplay |
| Events | ✅ 2 events | `PriceCalculated`, `TierApplied` |
| Tests | ✅ 9 files (2745 lines) | Dedicated monorepo tests + cross-package tests |
| Docs | ✅ 8 files | Full standard set + CONTEXT.md + README.md |
| Polymorphic relations | ✅ | `priceable` (MorphTo), `tierable` (MorphTo) |

---

## Summary

Solid, focused pricing package: 3 models (PriceList, Price, PriceTier), 4 actions, 2 events, 5 contracts, 2 DTOs, 2 settings, `PriceCalculator` service with a 4-step resolution pipeline. Money stored as integer minor units throughout. Owner scoping on all models with both query-level and write-path enforcement. No `$guarded` issues, no DB constraints.

8 doc files covering all aspects. 9 dedicated test files (2745 lines) including dedicated cross-tenant isolation tests. DTO-oriented design with `PriceResultData` and `TierPriceResultData` providing clean calculation results.

**Verdict:** Ready. No material issues.
