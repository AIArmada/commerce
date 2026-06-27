# Audit: `products` (AIArmada\Products)

**Status:** Ready with minor improvements

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Catalog products, variants, attributes, categories, collections, and product-domain behavior.

**Surface:** domain

---

## Findings

### Low
1. **`$guarded = ['id']` on all 10 models instead of explicit `$fillable`** — Deviates from the repo convention (most other packages use `$fillable`). New columns added to the DB become automatically mass-assignable. Consider switching to explicit `$fillable`.
2. **No exception hierarchy** — 5 actions, 5 events, 0 custom exceptions. Uses `InvalidArgumentException` inline.
3. **Policy helper duplication** — `belongsToOwner()` and `isGlobalModel()` duplicated identically across all 6 policy files. DRY violation.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| `$guarded` usage | ⚠️ `['id']` | All 10 models use `$guarded = ['id']`, not `$fillable` |
| Money storage | ✅ Integer minor units | `unsignedBigInteger` for price, compare_price, cost |
| Owner scoping | ✅ Excellent | 100% coverage, `booted()` hooks on all models, custom pivot scoping |
| Migrations | ✅ Clean | No `constrained()`, no `cascadeOnDelete()`, no FK constraints |
| Actions | ✅ 5 classes | CreateProduct, UpdateProduct, UpdateProductStatus, GenerateVariants, ApplyAttributeChanges |
| Events | ✅ 5 events | Full lifecycle — created, updated, deleted, status changed, variants generated |
| Contracts | ✅ 4 interfaces | Buyable, Priceable, Inventoryable, VariantGeneratorInterface |
| Enums | ✅ 6 enums | ProductStatus, ProductType, ProductVisibility, CatalogStatus, AttributeType, Visibility |
| Policies | ✅ 6 policy files | Product, Category, Collection, Attribute, AttributeGroup, AttributeSet |
| Tests | ✅ 19 files | Monorepo tests covering models, actions, policies, events, enums, scoping |
| Docs | ✅ 7 files | Full standard set + models reference |
| Translations | ✅ en + ms | Enum labels for both locales |
| Media support | ✅ spatie/medialibrary | 7 media collections, 5 image conversions |
| Slug support | ✅ spatie/sluggable | Product, Category, Collection via `HasSlug` |

---

## Summary

Rich, well-engineered package: 10 models (Product, Variant, Category, Collection, Attribute, AttributeGroup, AttributeSet, AttributeValue, Option, OptionValue), 6 enums, 5 actions, 5 events, 4 contracts, 6 policies. Attributes system with 9 types, variant generation with strategy pattern, hierarchical categories, rule-based collections, Spatie media library integration.

Money as integer minor units. Owner scoping on all 10 models with thorough `booted()` hooks and custom pivot-scoping for cross-tenant safety. Config-driven table names, `jsonb` for JSON columns, composite unique indexes for tenant isolation.

19 test files. 7 documentation files. 2 locale translations.

**Verdict:** Ready with minor improvements. `$guarded = ['id']` is the main deviation from repo convention — other packages use explicit `$fillable`.
