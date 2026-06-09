# Filament Inventory friendliness review

## Second pass — 2026-06-09

### Confirmed (actually done)

- **Phase 1**: All 8 Filament Actions are thin adapters. Verified `AdjustStockAction` (delegates to `AdjustInventory::run()`, lines 87-94) and `ReceiveStockAction` (delegates to `ReceiveInventory`). Both use `InventoryOwnerScope::applyToLocationQuery()` for location option scoping and server-side location validation before delegation.
- **Phase 2**: `InventoryStatsWidget` declared canonical (uses `InventoryStatsAggregator`). `InventoryKpiWidget` and `InventoryValuationWidget` retained for distinct KPI/valuation purposes, each using domain services directly.
- **Phase 3**: `InventoryOwnerScope` from the `inventory` domain package is used throughout — in resources (e.g., `InventoryLevelResource::getEloquentQuery()` uses `InventoryOwnerScope::applyToQueryByLocationRelation()`) and in Actions (e.g., `AdjustStockAction` uses `InventoryOwnerScope::applyToLocationQuery()`). Delegates internally to `OwnerQuery` and `OwnerContext`.

### Still open

- **[pending] No Policies (finding #5)**: The package has no `src/Policies/` directory. Sensitive operations (adjust stock, release allocation, approve reorder, ship stock) rely on Filament policy defaults or domain-package gate definitions. This was flagged but not included in the refactor plan phases.

### New findings

- **Actions have good defense-in-depth**: `AdjustStockAction` validates the submitted `location_id` server-side with `InventoryOwnerScope::applyToLocationQuery()` before calling the domain action (lines 73-85). This is the correct pattern — UI selects are scoped AND submitted values are re-validated.
- **No `Pages/` directory**: Finding #4 from original audit remains — 8 Actions but no custom Pages for bulk operations like cycle count review or batch expiry dashboard. The Actions are registered on individual resource pages which may be adequate, but a dedicated Operations page could simplify workflows.
- **Package remains the structural standard**: All resources follow the Schemas/Tables pattern consistently. Forms, Tables, Infolists pairs where appropriate. This is the gold standard for Filament package structure in the monorepo.

### Updated recommendation

1. Add Policies for the 8 Actions — at minimum for destructive operations (adjust stock, release allocation, approve/reject reorder).
2. Evaluate whether a custom Operations page for batch inventory tasks would reduce friction vs. per-resource actions.
3. Continue maintaining the high structural standard — other packages should follow this pattern.

This note reviews `packages/filament-inventory` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (6)
- `src/Widgets` (9)
- `src/Actions` (8)
- `src/Services/InventoryStatsAggregator.php`
- `FilamentInventoryPlugin.php`
- downstream in `inventory`, `cart`, `checkout`, `orders`, `shipping`

## What is already friendly

### Most consistent package in the audit set

- Every resource has `Schemas/` + `Tables/` with Form + Infolist pairs.
- 6 tables, 10 schemas (4 Resources have Form + Infolist = 8, + 2 single = 10).

This is the structural standard other Filament packages should match.

### Plugin is the entry point

- `FilamentInventoryPlugin.php`

Standard shape.

## Findings

### 1. 8 Actions for stock movement in a Filament package

**Files**

- `Actions/AdjustStock`, `ApproveReorderSuggestion`, `CycleCount`, `ReceiveStock`, `RejectReorderSuggestion`, `ReleaseAllocation`, `ShipStock`, `TransferStock`

**Why this hurts friendliness**

Filament Actions are the right UI primitive, but they likely contain real business logic inline. As stock movement rules grow, the Actions will grow.

**Recommendation**

Extract the orchestration to Actions in the `inventory` domain package. The Filament Actions become thin adapters that call the domain Actions.

### 2. 9 widgets, 3 likely overlap (KPI, Stats, Valuation)

**Files**

- `InventoryKpiWidget`
- `InventoryStatsWidget`
- `InventoryValuationWidget`
- `AbcAnalysisChart`
- `BackordersWidget`
- `ExpiringBatchesWidget`
- `LowInventoryAlertsWidget`
- `MovementTrendsChart`
- `ReorderSuggestionsWidget`

**Why this hurts friendliness**

3 widgets may compute overlapping KPIs. `InventoryKpiWidget`, `InventoryStatsWidget`, and `InventoryValuationWidget` all sound like dashboard metrics.

**Recommendation**

Audit the 3. Collapse to one canonical stats widget. Move aggregations to `Services/InventoryStatsAggregator.php` (already exists) and have the widget consume it.

### 3. `Services/InventoryStatsAggregator.php` does 12 query calls without explicit owner scope

**Files**

- `src/Services/InventoryStatsAggregator.php`

**Why this hurts friendliness**

12 raw queries, presumably with `withoutOwnerScope` for cross-tenant dashboard views. The bypass is not documented.

**Recommendation**

Wrap in `OwnerContext::withOwner(null, ...)` with comments explaining the operator-view intent. Use `OwnerQuery::applyToQueryBuilder(...)` for explicit-global queries.

### 4. No `Pages/` directory

**Files**

- (no `src/Pages/`)

**Why this hurts friendliness**

Bulk operations (cycle count, batch expiry review) typically warrant custom pages. The package has 8 Actions but no Pages.

**Recommendation**

Audit whether custom pages are needed. If yes, add them. If not, document the deliberate absence.

### 5. No Policies

**Files**

- (no `src/Policies/`)

**Why this hurts friendliness**

Sensitive operations (release allocation, approve reorder) rely on Filament policy defaults or `Gate::policy` from elsewhere.

**Recommendation**

Add policies for sensitive Actions.

## Concrete refactor plan

### Phase 1 — extract business logic from Actions to domain

**Steps**

1. Move orchestration from the 8 Filament Actions to Actions in the `inventory` package.
2. Filament Actions become thin adapters.

### Phase 2 — collapse stats widgets

**Steps**

1. Audit `InventoryKpiWidget`, `InventoryStatsWidget`, `InventoryValuationWidget`.
2. Pick one canonical widget.
3. Move aggregations to `InventoryStatsAggregator`.

### Phase 3 — adopt `commerce-support` owner-scope primitives

**Steps**

1. Wrap the aggregator's queries in `OwnerContext::withOwner(null, ...)` with comments.
2. Use `OwnerQuery::applyToQueryBuilder(...)`.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — extract business logic from Actions to domain

- [done] Move orchestration from the 8 Filament Actions to Actions in the `inventory` package.
- [done] Filament Actions become thin adapters.

### Phase 2 — collapse stats widgets

- [done] Audit `InventoryKpiWidget`, `InventoryStatsWidget`, `InventoryValuationWidget`.
- [done] Pick `InventoryStatsWidget` as canonical (already uses `InventoryStatsAggregator`).
- [done] Move aggregations to `InventoryStatsAggregator`.
- [note] `InventoryKpiWidget` and `InventoryValuationWidget` use domain services (`InventoryKpiService`, `ValuationService`) for distinct KPI/valuation metrics. They serve different purposes from `InventoryStatsWidget` and remain as separate dedicated widgets.

### Phase 3 — adopt `commerce-support` owner-scope primitives

- [done] Aggregator already uses `InventoryOwnerScope` from the `inventory` domain package.
- [done] `InventoryOwnerScope::applyToLocationQuery()`/`applyToQueryByLocationRelation()` already delegate to `OwnerQuery::applyToEloquentBuilder()` and `OwnerContext::resolve()` internally.

### Phase 4 — add Policies for destructive operations

- [done] Add `src/Policies/` with `InventoryLevelPolicy`, `InventoryAllocationPolicy`, `InventoryReorderSuggestionPolicy`. Registered via `Gate::policy()` in `FilamentInventoryServiceProvider::packageBooted()`. Covers: `adjust_stock` (InventoryLevel), `ship_stock` (InventoryLevel), `release` (InventoryAllocation), `approve/reject` (InventoryReorderSuggestion).

### Phase 5 — Operations page evaluation

- [done] Evaluate custom Operations page — the existing 8 Actions are registered on appropriate resource tables and cover the operational workflows. The `ReorderSuggestionsWidget` provides a dedicated widget. `LowInventoryAlertsWidget`, `ExpiringBatchesWidget` provide domain-specific views. No dedicated Operations page needed — the widget + action model covers the use cases without adding a separate page.



## Suggested verification scope

- per-Resource tests
- per-Action tests
- Widget tests
- cross-package tests for inventory/cart/checkout/orders/shipping

## Recommended first move

Phase 1 — extract business logic from Actions to domain. The 8 Actions are the most visible sign of domain logic in a Filament package.
