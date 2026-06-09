# Filament Products friendliness review

## Second pass — 2026-06-09

### Confirmed (actually done)

- **Phase 1**: Schemas/Tables subfolders exist for all 6 resources (`ProductResource/Schemas/`, `ProductResource/Tables/`, and equivalents for Category, Collection, AttributeGroup, AttributeSet, Attribute) — all present and consumed.
- **Phase 1**: `BaseProductResource`, `BaseAttributeResource`, `BaseCatalogResource` all exist and are extended by concrete resources.
- **Phase 2**: `Support/ProductStatsAggregator.php` exists (54 lines, uses `OwnerContext` for resolution, wraps queries in explicit global context when needed). `ProductStatsWidget` consumes it via `app(ProductStatsAggregator::class)` — clean delegation.
- **Phase 4**: `PricesRelationManager` kept in filament-products with `class_exists(Price::class)` gating. filament-pricing's `PriceListResource/RelationManagers/PricesRelationManager` is the canonical surface. Cross-reference exists.

### Still open

- **[pending] Phase 3 — orphaned pages not deleted**: `BulkEditProducts.php` (268 lines) and `ImportExportProducts.php` (354 lines) still exist on disk. The Plugin's `getPages()` returns `[]` and the functionality was moved to `ProductsTable` header/bulk actions. These files are dead code and should be deleted.

### New findings

- **PricesRelationManager lacks explicit owner scoping on query**: The `modifyQueryUsing` in `PricesRelationManager::table()` only joins `price_lists` for sorting — no owner scope is applied. While the relationship (`product->prices()`) may inherit scoping from the parent product, this is implicit and fragile. Recommend adding `getEloquentQuery()` or a `modifyQueryUsing` that applies owner scope explicitly via `OwnerQuery`, or at minimum document that the relationship layer provides scoping.

### Updated recommendation

1. Delete `BulkEditProducts.php` and `ImportExportProducts.php` — they are dead code.
2. Audit `PricesRelationManager` owner scoping — add explicit scope or document the implicit relationship-level protection.

This note reviews `packages/filament-products` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (6)
- `src/Pages` (2)
- `src/Widgets` (4)
- downstream in `products`, `cart`, `checkout`, `pricing`, `inventory`, `vouchers`, `events`

## What is already friendly

### Resource hierarchy is correct

- `ProductResource` (with RMs: Options, Prices, Variants)
- `Attribute*Resource` × 3 (Group, Set, Attribute)
- `CategoryResource`, `CollectionResource`

PIM-style hierarchy is in place.

### Plugin is the entry point

- `FilamentProductsPlugin.php`

## Findings

### 1. No `Schemas/` or `Tables/` subfolders

**Files**

- All 6 resources have inline Forms/Tables/Infolists.

**Why this hurts friendliness**

`ProductResource` is the central catalog resource. Inline Forms/Tables make the file hard to navigate and impossible to share between resources (e.g. Attribute forms).

**Recommendation**

Split into subfolders:

- `ProductResource/Schemas/{ProductForm, ProductInfolist}.php`
- `ProductResource/Tables/ProductsTable.php`
- Same for `Attribute*Resource`, `CategoryResource`, `CollectionResource`

### 2. `ProductStatsWidget` is the heaviest widget in the audit set

**Files**

- `Widgets/ProductStatsWidget.php` (7 query calls)

**Why this hurts friendliness**

A single widget with 7 raw queries suggests inline aggregation. As products grow, this widget will get slower.

**Recommendation**

Move the aggregation into a `Support/ProductStatsAggregator.php` service that the widget consumes. This makes the query pattern testable and reusable.

### 3. `BulkEditProducts` and `ImportExportProducts` should be Resource actions, not custom Pages

**Files**

- `src/Pages/BulkEditProducts.php`
- `src/Pages/ImportExportProducts.php`

**Why this hurts friendliness**

Custom Pages for bulk operations diverge from the standard Filament pattern. The Resource's Table is the right place for bulk actions.

**Recommendation**

Convert to Table actions on `ProductsTable`. Drop the custom Pages.

### 4. `ProductResource/Prices` RM duplicates `filament-pricing`'s price surface

**Files**

- `ProductResource/RelationManagers/PricesRelationManager.php` (inferred)
- `packages/filament-pricing/src/Resources/PriceListResource/RelationManagers/PricesRelationManager.php`

**Why this hurts friendliness**

Two surfaces for the same data. Pricing and product prices may drift.

**Recommendation**

Pick one canonical surface. Add cross-navigation if both are needed.

### 5. `Attribute*` resources likely have near-identical forms

**Files**

- `AttributeResource/`, `AttributeGroupResource/`, `AttributeSetResource/`

**Why this hurts friendliness**

Three sibling resources with similar structure (Form, Infolist, Table). Each is hand-rolled.

**Recommendation**

Extract a `BaseAttributeResource` (similar to `BaseChipResource` in `filament-chip`) that owns the common form structure.

### 6. `CategoryResource` and `CollectionResource` are minimal

**Files**

- `CategoryResource/`, `CollectionResource/`

**Why this hurts friendliness**

Two related catalog taxonomies, both minimal. They may grow in parallel and stay in sync poorly.

**Recommendation**

Consider a shared `BaseCatalogResource` for taxonomy-style resources.

## Concrete refactor plan

### Phase 1 — split resources into subfolders

**Steps**

1. Move Forms/Tables/Infolists into `Schemas/` and `Tables/` for all 6 resources.
2. Add `BaseProductResource`, `BaseAttributeResource`, `BaseCatalogResource` for shared structure.

### Phase 2 — extract `ProductStatsAggregator`

**Steps**

1. Move query logic from `ProductStatsWidget` to `Support/ProductStatsAggregator.php`.
2. Widget consumes the service.

### Phase 3 — convert bulk operations to Table actions

**Steps**

1. Convert `BulkEditProducts` and `ImportExportProducts` to Table actions.
2. Drop the custom Pages.

### Phase 4 — decide on Prices RM

**Steps**

1. Audit `filament-pricing` and `filament-products` Prices RMs.
2. Pick one canonical surface.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — split resources into subfolders

- [done] Move Forms/Tables/Infolists into `Schemas/` and `Tables/` for all 6 resources.
- [done] Add `BaseProductResource`, `BaseAttributeResource`, `BaseCatalogResource` for shared structure.

### Phase 2 — extract `ProductStatsAggregator`

- [done] Move query logic from `ProductStatsWidget` to `Support/ProductStatsAggregator.php`.
- [done] Widget consumes the service.

### Phase 3 — convert bulk operations to Table actions

- [done] Convert `BulkEditProducts` and `ImportExportProducts` to Table actions. (Second pass: functionality moved to `ProductsTable` header/bulk actions, but the files still exist on disk.)
- [done] Delete orphaned `BulkEditProducts.php` and `ImportExportProducts.php` — dead code deleted along with their view files.

### Phase 4 — decide on Prices RM

- [done] Audit `filament-pricing` and `filament-products` Prices RMs.
- [done] Pick one canonical surface (filament-pricing's PriceListResource/PricesRelationManager; product-scoped PricesRM kept for convenience with cross-reference note).

### Phase 5 — PricesRelationManager owner scoping audit

- [done] Audit `PricesRelationManager` owner scoping — `modifyQueryUsing` only joins `price_lists` for sorting with no explicit owner scope applied.
- [done] Add explicit owner scope to `PricesRelationManager::getEloquentQuery()` via `OwnerQuery::applyToEloquentBuilder()` — uses config `pricing.owner.enabled` and `pricing.owner.include_global`.



## Suggested verification scope

- per-Resource tests
- Widget tests
- cross-package tests for pricing/inventory

## Recommended first move

Phase 1 — split resources into subfolders. The current shape is the most visible structural smell and the cleanup is mechanical.
