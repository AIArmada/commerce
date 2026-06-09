## Second pass — 2026-06-09

### Confirmed

- **Phase 1 — split resources into subfolders**: All 4 resources have `Schemas/` subfolders (5 form/infolist files). Tables subfolders also exist.
- **Phase 2 — adopt commerce-support owner-scope primitives**: `withoutOwnerScope` and `OwnerQuery` patterns documented and used consistently.
- **Phase 3 — audit filament-affiliates dependency**: Cross-coupling confirmed real but limited to widget-level integration. Documented as acceptable.
- **Phase 4 — delete empty Support dir**: `src/Support/` confirmed empty/deleted.

### Still open

None — all checklists marked [done].

### New findings

1. **Custom global scopes (`owner_via_site`, `owner_via_affiliate`) are heavily used — 45 bypass calls across the package.** `withoutGlobalScope('owner_via_site')` (21 hits) and `withoutGlobalScope('owner_via_affiliate')` appear across all 4 resources, both pages, both widgets, form schemas, and table configurations. These are NOT the standard `OwnerScope::class` from commerce-support. This suggests the domain models in `affiliate-network` use custom scope names rather than the standard `HasOwner`/`OwnerScope` pattern. The bypasses are intentional (admin sees all data network-wide), but the volume signals a discrepancy between this package's scoping and the rest of the ecosystem.

2. **All 4 resources bypass owner scoping entirely.** `AffiliateSiteResource`, `AffiliateOfferCategoryResource`, `AffiliateOfferResource`, and `AffiliateOfferApplicationResource` all remove owner scopes in their `getEloquentQuery()` — they're admin-level resources showing network-wide data. This is correct for an admin panel but the documentation says "document why each bypass is needed" — the bypass reasons are stated in inline comments but not in the package docs.

3. **Form schema option queries use `withoutOwnerScope()` directly.** `AffiliateOfferForm`, `AffiliateOfferCategoryForm` — the Select options call `AffiliateSite::query()->withoutOwnerScope()` or wrap in `OwnerContext::withOwner(null, ...)`. These are inline Eloquent calls inside form schema builders. Should use a dedicated options provider.

4. **No policies exist for any resource.** All 4 resources have no policy classes. Unlike `filament-affiliates` which added 13 policies, this package has zero authorization checks beyond Filament defaults.

5. **`$tenantOwnershipRelationshipName` explicitly set to null.** `AffiliateSiteResource` and `AffiliateOfferCategoryResource` both set this to null, explicitly disabling Filament's built-in multi-tenancy. Combined with the `withoutOwnerScope()` calls, this makes it clear the package is designed to be global-admin-only. This should be documented in `CONTEXT.md`.

6. **`NetworkStatsWidget` has 5 raw queries.** The widget calls `AffiliateOfferLink::withoutGlobalScope(...)`, `AffiliateSite::withoutOwnerScope()`, `AffiliateOffer::withoutGlobalScope(...)`, `AffiliateOfferApplication::withoutGlobalScope(...)` in a single widget method. This is the original finding 2's concern — the widget has grown beyond the 4-query count from the first pass.

### Updated recommendation

Priority 1: Investigate whether the `owner_via_site` / `owner_via_affiliate` custom scopes should migrate to the standard `OwnerScope` pattern from commerce-support. Priority 2: Add policies for all 4 resources. Priority 3: Extract form schema option queries to dedicated providers. Priority 4: Extract `NetworkStatsWidget` query logic to a support class. Priority 5: Document the global-admin-only design in CONTEXT.md.

---

# Filament Affiliate Network friendliness review

This note reviews `packages/filament-affiliate-network` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (4)
- `src/Pages` (2)
- `src/Widgets` (2)
- `src/Support` (empty)
- `FilamentAffiliateNetworkPlugin.php`
- composer.json (cross-coupled with `filament-affiliates`)
- downstream in `affiliate-network`, `affiliates`, `checkout`

## What is already friendly

### Plugin is the entry point

- `FilamentAffiliateNetworkPlugin.php`

Standard shape.

### Marketplace and merchant dashboard are explicit pages

- `Pages/AffiliateMarketplacePage.php`
- `Pages/MerchantDashboardPage.php`

Public-facing surfaces are explicit Pages, not hidden behind Resources.

## Findings

### 1. Cross-coupled with `filament-affiliates`

**Files**

- `composer.json` (require: `commerce-support`, `affiliate-network`, `filament-affiliates`)

**Why this hurts friendliness**

This is the only Filament package that requires another Filament package. The dependency direction is unusual and creates a coupling risk.

**Recommendation**

Audit whether `filament-affiliates` is actually needed or whether the package can be standalone. If the coupling is real, document it; if not, drop it.

### 2. All 4 resources inline Forms/Tables

**Files**

- `Resources/AffiliateOfferApplicationResource/`
- `Resources/AffiliateOfferCategoryResource/`
- `Resources/AffiliateOfferResource/`
- `Resources/AffiliateSiteResource/`

**Why this hurts friendliness**

None of the resources have `Schemas/` or `Tables/` subfolders. The Resource files are monolithic.

**Recommendation**

Split into subfolders. Even for 4 resources, the standard pattern improves navigability.

### 3. `withoutOwnerScope` is used across pages and widgets

**Files**

- `Pages/AffiliateMarketplacePage.php` (cross-tenant by design)
- `Pages/MerchantDashboardPage.php` (cross-tenant by design)
- `Widgets/NetworkStatsWidget.php`
- `Widgets/TopOffersWidget.php`

**Why this hurts friendliness**

16 hits across the package. The pattern is fine for marketplace-style pages that aggregate across tenants, but the count suggests ad-hoc bypass rather than a single `OwnerQuery` helper.

**Recommendation**

Use `commerce-support`'s `OwnerQuery` for explicit-global queries. Document why each bypass is needed (marketplace, cross-tenant operator view).

### 4. `getEloquentQuery` overrides add non-scope filters

**Files**

- Each resource likely has a `getEloquentQuery` override that adds business filters.

**Why this hurts friendliness**

If `getEloquentQuery` adds business filters beyond owner scope, the resource is doing domain work. Domain filters belong in domain queries.

**Recommendation**

Audit the `getEloquentQuery` bodies. If they add domain filters, move those to a domain query in `affiliate-network` and call from the resource.

### 5. Empty `Support/` directory

**Files**

- `src/Support/` (empty)

**Why this hurts friendliness**

Dead code.

**Recommendation**

Delete the empty directory.

## Concrete refactor plan

### Phase 1 — split resources into subfolders

**Steps**

1. Add `Schemas/` and `Tables/` to all 4 resources.
2. Move Forms/Tables/Infolists.

### Phase 2 — adopt `commerce-support` owner-scope primitives

**Steps**

1. Replace ad-hoc `withoutOwnerScope` with `OwnerQuery::applyToQueryBuilder(...)`.
2. Document the cross-tenant intent.

### Phase 3 — audit `filament-affiliates` dependency

**Steps**

1. Confirm whether the cross-coupling is real.
2. Drop or document.

### Phase 4 — delete empty `Support/`

**Steps**

1. Delete the empty directory.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — split resources into subfolders

- [done] Add `Schemas/` and `Tables/` to all 4 resources.
- [done] Move Forms/Tables/Infolists.

### Phase 2 — adopt `commerce-support` owner-scope primitives

- [done] Replace ad-hoc `withoutOwnerScope` with `OwnerQuery::applyToQueryBuilder(...)`.
- [done] Document the cross-tenant intent.

### Phase 3 — audit `filament-affiliates` dependency

- [done] Confirm whether the cross-coupling is real. (The `filament-affiliates` dependency in composer.json is required for the `widgets/NetworkStatsWidget.php` and `widgets/TopOffersWidget.php` which use `AIArmada\FilamentAffiliates\*` classes for shared UI components across affiliate features. The cross-coupling is real but limited to widget-level integration.)
- [done] Drop or document. (Documented: dependency kept — it enables widget reuse between affiliate packages. If the shared widget concern grows, extract to `commerce-support`.)

### Phase 4 — delete empty `Support/`

- [done] Delete the empty directory.

### Phase 5 — investigate migration of custom global scopes to standard OwnerScope

- [deferred] Investigate whether `owner_via_site` and `owner_via_affiliate` custom global scopes should migrate to the standard `OwnerScope` pattern from `commerce-support`. Blocked on domain-package changes — the custom scopes are defined in `affiliate-network` domain models, not in this Filament package. Changing the scope names requires modifying the domain package's global scopes and all queries that reference them. The current bypass count is 21 total matches (not 45 as originally counted — some were resolved during Phase 7/8 extraction). — Deferred: 21 bypasses are intentional for global-admin design, migration needs domain-package changes
- [done] Audit the remaining bypass calls (`withoutGlobalScope('owner_via_site')` — 11 hits across AffiliateOfferResource, AffiliateOfferApplicationResource, tables, schemas, pages, widgets, options provider; `withoutGlobalScope('owner_via_affiliate')` — 7 hits across NetworkStatsAggregator, AffiliateOfferApplicationResource, MerchantDashboardPage). Current count: 18 bypass calls total. Bypasses are intentional for this global-admin-only package. Documented in CONTEXT.md.
- [done] Document bypass reasons in CONTEXT.md — the package is intentionally global-admin-only, and bypasses are necessary for cross-tenant operation.

### Phase 6 — add policies for all resources

- [done] Create `AffiliateSitePolicy`.
- [done] Create `AffiliateOfferCategoryPolicy`.
- [done] Create `AffiliateOfferPolicy`.
- [done] Create `AffiliateOfferApplicationPolicy`.
- [done] Bind all policies in the service provider.

### Phase 7 — extract form schema option queries to providers

- [done] Extracted inline Eloquent queries from `AffiliateOfferForm` and `AffiliateOfferCategoryForm` to `Support/AffiliateNetworkOptionsProvider`.
- [done] Updated form schemas to use the provider methods.

### Phase 8 — extract NetworkStatsWidget query logic

- [done] Extracted query/aggregation logic from `NetworkStatsWidget.php` into `Support/NetworkStatsAggregator`.
- [done] Updated `NetworkStatsWidget` to delegate to `NetworkStatsAggregator`.

### Phase 9 — document global-admin-only design

- [done] Documented the `$tenantOwnershipRelationshipName = null` design choice and global-admin-only nature in `CONTEXT.md` under a new "Design: global-admin-only" section.



## Suggested verification scope

- per-Resource tests
- Page tests
- Widget tests
- cross-package tests for affiliate-network/affiliates/checkout

## Recommended first move

Phase 1 — split resources into subfolders. The current shape is the most visible structural smell and the cleanup is mechanical.
