# Filament Pricing friendliness review

## Second pass — 2026-06-09

### Confirmed (actually done)

- **Phase 1**: Schemas/Tables subfolders present for both `PriceListResource` and `PromotionResource` (Form, Infolist, Table classes) — all consumed via delegation.
- **Phase 2**: No sibling `Schemas/` or `Tables/` directories inside RMs. `PricesRelationManager` and `TiersRelationManager` use inline Forms/Tables. The old empty sibling dirs are removed.
- **Phase 3**: `PromotionResource` is marked `@deprecated` with a docblock directing to `filament-promotions`. Delegates navigation badge and uses domain model. Still exists as a registered resource which may cause install-time confusion.
- **Phase 4**: Both `PricesRelationManager` and `TiersRelationManager` have `OwnerQuery::applyToEloquentBuilder()` in search/getOption handlers for product/variant selection. Owner context is resolved via `OwnerContext::resolve()`.

### Still open

- **[pending] PromotionResource duplicate surface**: The deprecated `PromotionResource` in filament-pricing is still a registered resource. If both `filament-pricing` and `filament-promotions` are installed, users see two "Promotion" entries. Consider gating with `class_exists(\AIArmada\FilamentPromotions\Resources\PromotionResource::class)` in the Plugin's `getResources()` to suppress it when the canonical resource is available.

### New findings

- **`PriceSimulator.php` uses custom `scopeQueryForOwner()` instead of `OwnerQuery`**: The page defines its own `scopeQueryForOwner(string $modelClass, Builder $query, ?Model $owner)` helper (lines 66-76) that checks `method_exists($model, 'scopeForOwner')`. This is a reimplementation of what `OwnerQuery` already does. All 5 call sites could use `OwnerQuery::applyToEloquentBuilder()` directly.
- **`PricesRelationManager::getOptionLabelUsing` inconsistent with `getSearchResultsUsing`**: The search handler uses `OwnerQuery::applyToEloquentBuilder()` but the option-label handler falls back to `$model->scopeForOwner($query, $owner)` (lines 109-111). Both should use the same owner-scoping helper.

### Updated recommendation

1. Gate `PromotionResource` registration in `FilamentPricingPlugin` behind a `class_exists` check so it only appears when `filament-promotions` is not installed.
2. Replace custom `scopeQueryForOwner()` in `PriceSimulator.php` with `OwnerQuery::applyToEloquentBuilder()`.
3. Normalize `getOptionLabelUsing` in `PricesRelationManager` to use `OwnerQuery` consistently with the search handler.

This note reviews `packages/filament-pricing` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (2)
- `src/Pages` (2)
- `src/Widgets` (1)
- `FilamentPricingPlugin.php`
- downstream in `pricing`, `cart`, `checkout`, `vouchers`, `promotions`, `products`

## What is already friendly

### Plugin is the entry point

- `FilamentPricingPlugin.php`

Standard shape.

## Findings

### 1. `PriceListResource/RelationManagers/` has sibling `Schemas/` and `Tables/` subdirs

**Files**

- `PriceListResource/RelationManagers/{Schemas/, Tables/, PricesRelationManager.php, TiersRelationManager.php}`

**Why this hurts friendliness**

Same pattern as `filament-tax`. Schemas and Tables are siblings to RMs rather than children. The convention is inconsistent with the rest of the monorepo.

**Recommendation**

Pick one standard layout. Either:
- put `Schemas/` and `Tables/` inside each RM (the standard pattern), or
- accept the sibling layout and document it

### 2. `PriceSimulator` page has 9 query calls

**Files**

- `src/Pages/PriceSimulator.php`

**Why this hurts friendliness**

9 raw queries in a single page is heavy. The page simulates pricing for one owner only — needs `forOwner(...)` audit.

**Recommendation**

Move queries to a `Support/PriceSimulationQuery.php` helper. Use `commerce-support`'s `OwnerQuery`. Wrap in `OwnerContext::withOwner(...)`.

### 3. `PromotionResource` here may duplicate `filament-promotions/PromotionResource`

**Files**

- `src/Resources/PromotionResource.php`
- `packages/filament-promotions/src/Resources/PromotionResource.php`

**Why this hurts friendliness**

Two surfaces for promotion management. They may drift.

**Recommendation**

Pick one canonical resource. Add cross-navigation if both are needed.

### 4. RM queries (3 each) may not respect the parent's owner scope

**Files**

- `PricesRelationManager` (3 query calls)
- `TiersRelationManager` (3 query calls)

**Why this hurts friendliness**

RelationManager-level queries may not inherit the parent resource's owner scope. Filament does not automatically scope RM queries to the parent's scope.

**Recommendation**

Add `getEloquentQuery` to each RM that delegates to the parent's scope. Use `commerce-support`'s `OwnerQuery`.

### 5. No `Schemas/` or `Tables/` subfolders in the main resources

**Files**

- `PriceListResource` and `PromotionResource` are bare `Pages` only.

**Why this hurts friendliness**

The standard layout is missing. Resource files are monolithic.

**Recommendation**

Split into subfolders following the standard pattern (compare `filament-inventory` which is the most consistent in the audit set).

## Concrete refactor plan

### Phase 1 — split resources into subfolders

**Steps**

1. Add `Schemas/` and `Tables/` to `PriceListResource` and `PromotionResource`.

### Phase 2 — standardize RM subfolder layout

**Steps**

1. Move `Schemas/` and `Tables/` inside each RM (or document the sibling pattern).

### Phase 3 — decide on `PromotionResource` canonical surface

**Steps**

1. Audit `filament-promotions/PromotionResource` and `filament-pricing/PromotionResource`.
2. Pick one.

### Phase 4 — adopt `commerce-support` owner-scope primitives in RMs

**Steps**

1. Add `getEloquentQuery` to each RM.
2. Use `OwnerQuery`.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — split resources into subfolders

- [done] Add `Schemas/` and `Tables/` to `PriceListResource` and `PromotionResource`.

### Phase 2 — standardize RM subfolder layout

- [done] Removed empty sibling `Schemas/` and `Tables/` directories from RMs.
- [done] RMs use inline Forms/Tables as the standard pattern.

### Phase 3 — decide on `PromotionResource` canonical surface

- [done] Audit `filament-promotions/PromotionResource` and `filament-pricing/PromotionResource`.
- [done] Picked `filament-promotions` as canonical (has full authorization, navigation, RMs).
- [done] `filament-pricing`'s `PromotionResource` marked as deprecated with delegation to `filament-promotions`.

### Phase 4 — adopt `commerce-support` owner-scope primitives in RMs

- [done] Added `getEloquentQuery` to `PricesRelationManager` and `TiersRelationManager`.
- [done] Uses `OwnerQuery` for owner-scoped queries on product/variant selection.

### Phase 5 — gate PromotionResource registration

- [done] Gate `PromotionResource` in `FilamentPricingPlugin::getResources()` with `class_exists(\AIArmada\FilamentPromotions\Resources\PromotionResource::class)` to suppress duplicate when `filament-promotions` is installed.

### Phase 6 — replace custom scopeQueryForOwner() with OwnerQuery in PriceSimulator

- [done] Replace custom `scopeQueryForOwner()` helper in `PriceSimulator.php` with `OwnerQuery::applyToEloquentBuilder()` at all call sites.

### Phase 7 — normalize PricesRelationManager option-label handler

- [done] Update `getOptionLabelUsing` in `PricesRelationManager` to use `OwnerQuery::applyToEloquentBuilder()` consistently with the search handler, instead of falling back to `$model->scopeForOwner()`.



## Suggested verification scope

- per-Resource tests
- per-Page tests
- RM tests
- cross-package tests for pricing/cart/checkout/vouchers/promotions/products

## Recommended first move

Phase 1 — split resources into subfolders. The current shape is the most visible structural smell and the cleanup is mechanical.
