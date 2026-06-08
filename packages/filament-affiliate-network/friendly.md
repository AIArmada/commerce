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

- [pending] Add `Schemas/` and `Tables/` to all 4 resources.
- [pending] Move Forms/Tables/Infolists.

### Phase 2 — adopt `commerce-support` owner-scope primitives

- [pending] Replace ad-hoc `withoutOwnerScope` with `OwnerQuery::applyToQueryBuilder(...)`.
- [pending] Document the cross-tenant intent.

### Phase 3 — audit `filament-affiliates` dependency

- [pending] Confirm whether the cross-coupling is real.
- [pending] Drop or document.

### Phase 4 — delete empty `Support/`

- [pending] Delete the empty directory.



## Suggested verification scope

- per-Resource tests
- Page tests
- Widget tests
- cross-package tests for affiliate-network/affiliates/checkout

## Recommended first move

Phase 1 — split resources into subfolders. The current shape is the most visible structural smell and the cleanup is mechanical.
