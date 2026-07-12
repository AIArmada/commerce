# Design Record: Owner Scope Consolidation

- **Task:** DES-OWN-410
- **Date:** 2026-07-12
- **Status:** Proposed — implementation gated on reviewer approval
- **Chosen design:** Design B — no new OwnerAccessPolicy; reuse `commerce-support` directly and remove only duplicate tuple adapters

## Observed facts

1. `OwnerScopeConfig` already carries enabled state, global-row policy, resolved owner, configurable tuple columns, and auto-assignment policy, and reads a package configuration subtree through `fromConfig()` (`packages/commerce-support/src/Support/OwnerScopeConfig.php:9-38`).
2. `OwnerQuery` applies the exact owner tuple semantics to both Eloquent and query-builder queries: no owner selects global-only rows; an owner selects its rows and optionally global rows (`packages/commerce-support/src/Support/OwnerQuery.php:11-84`).
3. `OwnerScope` requires an owner context or explicit global context before applying the configured tuple query (`packages/commerce-support/src/Support/OwnerScope.php:11-37`). `HasOwner::bootHasOwner()` installs that scope and the create/save/delete guards for a model whose resolved config is enabled (`packages/commerce-support/src/Traits/HasOwner.php:48-71`).
4. `HasOwnerScopeConfig` reads each model's `ownerScopeConfigKey` and creates the corresponding `OwnerScopeConfig` (`packages/commerce-support/src/Traits/HasOwnerScopeConfig.php:33-62`). `HasOwner::scopeForOwner()` then removes the ambient global scope, requires an explicit context when needed, and reapplies `OwnerQuery` with that model configuration (`packages/commerce-support/src/Traits/HasOwner.php:202-234`).
5. `OwnerWriteGuard::findOrFailForOwner()` delegates inbound-ID validation to `ResolveOwnedModelOrFailAction` (`packages/commerce-support/src/Support/OwnerWriteGuard.php:10-36`). This is a write-boundary tool, not a replacement for query scoping.
6. `PricingOwnerScope`, `ShippingOwnerScope`, `TaxOwnerScope`, and `PromotionsOwnerScope` have the same 44-line shape: package config reads, `OwnerContext::resolve()`, a `scopeForOwner` preference, then `OwnerQuery` fallback (`packages/pricing/src/Support/PricingOwnerScope.php:12-55`; `packages/shipping/src/Support/ShippingOwnerScope.php:12-55`; `packages/tax/src/Support/TaxOwnerScope.php:12-55`; `packages/promotions/src/Support/PromotionsOwnerScope.php:12-55`).
7. Their callers predominantly query models that already use `HasOwner` and `HasOwnerScopeConfig`: for example PriceList (`packages/pricing/src/Models/PriceList.php:42-53`), Promotion (`packages/promotions/src/Models/Promotion.php:58-72`), Shipment (`packages/shipping/src/Models/Shipment.php:55-65`), and TaxZone (`packages/tax/src/Models/TaxZone.php:42-55`). The helpers therefore duplicate model-config resolution rather than add a package policy.
8. Inventory is materially different. `InventoryOwnerScope` includes tuple scoping of a location query, relation traversal for allocations, a two-sided movement query, global-only inspection, and a cache-key suffix (`packages/inventory/src/Support/InventoryOwnerScope.php:12-109`). Those relations and cache identity have no useful meaning for pricing, tax, promotions, or shipping.
9. The foundation provider is `packages/commerce-support/src/SupportServiceProvider.php`, whose package registration sets the owner resolver and whose boot sequence fails fast when owner mode is enabled with no valid resolver (`packages/commerce-support/src/SupportServiceProvider.php:34-56`, `packages/commerce-support/src/SupportServiceProvider.php:273-348`). `CommerceSupportServiceProvider.php` does not exist.

## Inferences

1. **Inference:** A new `OwnerAccessPolicy` would duplicate four existing responsibilities — config, tuple query, global scope, and write validation — and would force callers to choose between two authoritative owner APIs.
2. **Inference:** The common unit is precisely tuple scoping and write-ID validation. It belongs to `commerce-support` and is already implemented; packages should configure `HasOwnerScopeConfig`, rely on the model's global scope, use `forOwner()`/`globalOnly()` for explicit reads, and use `OwnerWriteGuard` for submitted IDs.
3. **Inference:** The four identical package helper classes are removable adapters, not a justification for a new extension seam. Their deletion reduces policy drift without moving package-specific business behavior into the foundation.
4. **Inference:** Inventory's relation traversal and cache identity are domain behavior. Deleting or generalising them into an OwnerAccessPolicy would either create an underspecified generic API or make every package depend on Inventory concepts.
5. **Inference:** Owner scope is a query/write authorization boundary, not a transaction or retry workflow. Transactions remain owned by the business action; retries must re-enter an owner context and rerun the same owner-safe query/guard.

## Design alternatives

### Design A — introduce `OwnerAccessPolicy`

Create a new `commerce-support` policy facade that decides reads, writes, global access, and relationship scope for every package. Migrate all current helpers behind it.

### Design B — direct reuse of existing foundation primitives (chosen)

Do not add a module. Delete only the identical Pricing, Promotions, Shipping, and Tax adapters. Model paths rely on `HasOwner` + `HasOwnerScopeConfig`; explicit read paths use `forOwner()` or `globalOnly()`; query-builder paths use `OwnerQuery`; inbound IDs use `OwnerWriteGuard`. Keep `InventoryOwnerScope` as the Inventory relation/cache adapter.

### Design C — preserve every package helper and document a convention

Leave the duplicated helpers in place, prohibit a new policy, and try to keep their copies aligned through documentation/review.

| Dimension | A — new policy | B — direct foundation reuse | C — retain helper copies |
| --- | --- | --- | --- |
| Depth | Deep new abstraction over existing abstractions | Shallow deletion and direct use of proven primitives | Shallow code change, but ongoing conceptual duplication |
| Leverage | Low; no new variation is represented | High; one tuple contract applies to every configured model | Low; every improvement must be copied |
| Locality | Poor; foundation learns package relation semantics | High; tuple logic stays foundation, Inventory semantics stay Inventory | Medium; local but repeated |
| Caller knowledge | Medium; callers learn another facade | Low; callers use model scope/guard already in the contract | Medium; callers learn package aliases |
| Test surface | Large cross-package policy matrix | Focused foundation contract plus affected call-site regressions | Repeated helper tests and drift risk |
| Migration cost | High | Low to medium | None now, recurring later |

## Chosen design

### Recommendation and seam

Choose **Design B** and explicitly reject creation of `OwnerAccessPolicy`. The stable external seams already are:

- `HasOwner` + `HasOwnerScopeConfig` for a tenant-owned Eloquent model and its configured global scope;
- `Model::forOwner()` and `Model::globalOnly()` for intentionally selected read context;
- `OwnerQuery` for query-builder or relation shapes that cannot use an owned model directly; and
- `OwnerWriteGuard::findOrFailForOwner()` for inbound IDs on a write path.

This is **direct in-process reuse**, not a pluggable provider interface: no genuine alternate adapter exists. The only package configuration needed is each model's existing `ownerScopeConfigKey` plus package owner settings. The actual foundation registration remains `SupportServiceProvider`; no provider rename or extra provider binding is approved.

### Required invariants

- `owner = null` means global-only, never all owners. An enabled owner-scoped model with missing ambient owner must fail unless code enters explicit global context.
- Tenant-owned models retain `HasOwner`, `HasOwnerScopeConfig`, and their package config key. Removing a package helper must not remove the model global scope or write guards.
- Read operations use the model's ambient global scope unless a cross-context use case deliberately calls `forOwner()` or `globalOnly()`; removing a global scope must immediately reapply an intentional owner selection.
- Query-builder paths use `OwnerQuery` with the correct table-qualified tuple columns. Relationship options and aggregates receive the same owner treatment as record queries.
- Inbound IDs are revalidated with `OwnerWriteGuard` (or the underlying resolve action) at the mutation boundary; a filtered UI option is never sufficient.
- `InventoryOwnerScope` remains the owner of `applyToLocationQuery`, `applyToQueryByLocationRelation`, `applyToMovementQuery`, and `cacheKeySuffix`. No common tuple cleanup changes its relationship or cache behavior.

### Errors, transactions, and retries

The scope decision creates no durable state and begins no transaction. `OwnerScope` throws when an enabled model is queried without a resolved or explicit global context; owner-safe ID resolution returns the domain's normal not-found/authorization result. A caller's business action owns its transaction and must validate all inbound identifiers before mutation.

Queued jobs, commands, and retries must establish `OwnerContext::withOwner($owner, ...)` (or explicit global context) before querying. Repeating a query with the same owner and include-global policy is deterministic; retries do not cache or carry a mutable authorization decision. Owner-sensitive cache keys remain Inventory's `cacheKeySuffix` concern or use the existing `OwnerCache`/`OwnerScopeKey` primitives, never a new access-policy cache.

### Implementation shape

First migrate the four duplicate adapter call sites to the existing model/query primitives, then delete the helpers. Any custom `scopeForOwner` override that only reads the duplicate helper should delegate to `HasOwner`'s configured base scope and enforce package `include_global` at that explicit call boundary. Preserve package-specific model lifecycle checks and `OwnerWriteGuard` calls; they enforce relationships, not merely the owner tuple.

The deletion test passes for the four helpers: remove each and all common behavior still comes from `commerce-support`. It fails for `InventoryOwnerScope`: remove it and no remaining primitive can express location-relation scoping, two-sided movement ownership, or Inventory's cache partition.

## Implementation scope manifest

### Files to modify — common tuple scoping only

- `packages/pricing/src/Models/Price.php`
- `packages/pricing/src/Models/PriceList.php`
- `packages/pricing/src/Models/PriceTier.php`
- `packages/pricing/src/Actions/ResolveTierPrice.php`
- `packages/pricing/src/Services/PriceCalculator.php`
- `packages/pricing/src/Support/CustomerPriceResolver.php`
- `packages/pricing/src/Support/SegmentPriceResolver.php`
- `packages/pricing/src/Support/TierResolver.php`
- `packages/promotions/src/Models/Promotion.php`
- `packages/promotions/src/Support/PromotionPerformanceInsights.php`
- `packages/shipping/src/Actions/CreateShipment.php`
- `packages/shipping/src/Services/ShippingZoneResolver.php`
- `packages/shipping/src/Services/TrackingAggregator.php`
- `packages/tax/src/Models/TaxClass.php`
- `packages/tax/src/Models/TaxExemption.php`
- `packages/tax/src/Models/TaxRate.php`
- `packages/tax/src/Models/TaxZone.php`
- `packages/tax/src/Services/TaxCalculator.php`
- `packages/tax/src/Services/ZoneResolver/AddressZoneResolver.php`
- `packages/tax/src/Services/ZoneResolver/DefaultZoneResolver.php`
- `packages/tax/src/Services/ZoneResolver/ZoneIdResolver.php`

### Files to delete — duplicate tuple adapters

- `packages/pricing/src/Support/PricingOwnerScope.php`
- `packages/promotions/src/Support/PromotionsOwnerScope.php`
- `packages/shipping/src/Support/ShippingOwnerScope.php`
- `packages/tax/src/Support/TaxOwnerScope.php`

### Files explicitly out of the consolidation implementation

- `packages/inventory/src/Support/InventoryOwnerScope.php` — retain; it owns location relations, movement logic, and cache identity
- `packages/commerce-support/src/Support/OwnerScopeConfig.php` — reuse unchanged
- `packages/commerce-support/src/Support/OwnerQuery.php` — reuse unchanged
- `packages/commerce-support/src/Support/OwnerScope.php` — reuse unchanged
- `packages/commerce-support/src/Support/OwnerWriteGuard.php` — reuse unchanged
- `packages/commerce-support/src/SupportServiceProvider.php` — correct provider path; reuse unchanged

### Tests to create or update

- `tests/src/Pricing/Feature/PricingOwnerScopeConsolidationTest.php`
- `tests/src/Promotions/Feature/PromotionsOwnerScopeConsolidationTest.php`
- `tests/src/Shipping/Feature/ShippingOwnerScopeConsolidationTest.php`
- `tests/src/Tax/Feature/TaxOwnerScopeConsolidationTest.php`
- `tests/src/Inventory/Unit/InventoryOwnerScopeTest.php` — confirm Inventory-specific relation/cache behavior is retained
- `tests/src/CommerceSupport/OwnerScopeTest.php` — retain/extend only if the direct-call migration exposes an uncovered primitive contract

### Documentation to update

- `packages/commerce-support/docs/04-multi-tenancy.md`
- `packages/commerce-support/docs/10-traits-utilities.md`
- `packages/pricing/docs/04-usage.md`
- `packages/promotions/docs/04-usage.md`
- `packages/shipping/docs/04-usage.md`
- `packages/tax/docs/04-usage.md`

## Rejected alternatives

### Rejected: Design A — new OwnerAccessPolicy

It duplicates a deep, tested implementation and provides no second adapter or new behaviour. The deletion test fails: removing the proposed policy leaves all required common capabilities intact in the existing foundation, so it adds indirection rather than leverage.

### Rejected: Design C — keep helper copies

The helpers are byte-for-byte policy cousins that can drift in missing-owner, include-global, and future tuple-column behavior. Documentation cannot enforce consistency as reliably as routing all common behavior through the established primitives.

### Rejected: absorb InventoryOwnerScope into the foundation

Its relation topology and cache suffix are Inventory domain facts, not generic ownership facts. Generalising them would widen the foundation with a vocabulary no other package uses.

## Unknowns

1. Whether any third-party consumer imports one of the four package helper class names. This monorepo permits breaking changes, but the implementation issue should explicitly record the removal in package release notes.
2. Whether every listed caller is querying a model that uses `HasOwner`; any raw model/query-builder call must use `OwnerQuery` with an explicit config-derived tuple rather than assuming a global scope.
3. Whether the current package-specific `scopeForOwner` overrides intentionally constrain caller-supplied `includeGlobal` more strictly than their config. Preserve that constraint when rewriting the overrides; do not silently broaden global visibility.
4. Whether any Inventory cache use lacks `InventoryOwnerScope::cacheKeySuffix` or `OwnerCache`. That is an audit follow-up, not a reason to create OwnerAccessPolicy.
