# Filament Inventory - Lifecycle

## 1. Package Identity
- **Composer**: `aiarmada/filament-inventory`
- **Namespace**: `AIArmada\FilamentInventory`
- **Role**: Filament admin UI layer for inventory — provides resources, widgets, actions, and panel integration.
- **Domain boundary**: This package owns only Filament presentation (resources, pages, widgets, tables, forms, infolists, actions, and plugin/panel glue). Domain logic, persistence, and state transitions belong to the `inventory` package.
- **DB migrations**: None. This package has no migrations.

## 2. Registration & Boot
1. `FilamentInventoryServiceProvider::configurePackage()` registers the config file (`config/filament-inventory.php`) via PackageServiceProvider.
2. `packageRegistered()` binds `FilamentInventoryPlugin` and `InventoryStatsAggregator` as singletons.
3. `packageBooted()`:
   - Registers three Gate policies: `InventoryLevelPolicy`, `InventoryAllocationPolicy`, `InventoryReorderSuggestionPolicy`.
   - Registers two Livewire components for widgets: `InventoryStatsWidget` and `LowInventoryAlertsWidget`.
   - Registers an empty render hook on `panels::body.start` (plugin discovery placeholder).
4. `FilamentInventoryPlugin::register(Panel $panel)`:
   - Always registers: `InventoryLocationResource`, `InventoryLevelResource`, `InventoryMovementResource`, `InventoryAllocationResource`.
   - Conditionally registers `InventoryBatchResource` and `InventorySerialResource` based on `filament-inventory.features.batch_resource` and `filament-inventory.features.serial_resource`.
   - Always registers: `InventoryStatsWidget`, `LowInventoryAlertsWidget`.
   - Conditionally registers 7 additional widgets based on their feature flags (`expiring_batches_widget`, `reorder_suggestions_widget`, `backorders_widget`, `valuation_widget`, `kpi_widget`, `movement_trends_chart`, `abc_analysis_chart`).

## 3. Resources (6)

### InventoryLocationResource
- **Model**: `InventoryLocation`
- **Owner scoping**: `InventoryOwnerScope::applyToLocationQuery()` on `getEloquentQuery()`.
- **Pages**: `List` → `Create` → `View` → `Edit`. All four standard CRUD pages present.
- **Form fields**: `name`, `code` (unique), `line1`, `line2`, `city`, `state`, `postcode`, `country` (ISO2), `priority` (numeric), `is_active` (toggle), `metadata` (key-value).
- **Infolist**: Location details, statistics (SKU count, total on hand, total reserved), metadata.
- **Table**: Name, code (badge+copyable), address, priority (badge colored by range), is_active (boolean icon), SKU count (via `counts`), created_at. Filter by active/inactive. Actions: View, Edit, Delete (requires confirmation). Bulk delete. Default sort: priority desc.
- **Navigation badge**: Count of active locations (green).

### InventoryLevelResource
- **Model**: `InventoryLevel`
- **Owner scoping**: `InventoryOwnerScope::applyToQueryByLocationRelation()` via location.
- **Pages**: `List` → `View` → `Edit`. No Create page.
- **Form fields**: `location_id` (disabled select via relationship), `inventoryable_type` (disabled), `quantity_on_hand`, `quantity_reserved` (disabled), `reorder_point`, `allocation_strategy`.
- **Infolist**: Stock details (location, product type/id), quantities (on hand, reserved, available, reorder point), configuration (allocation strategy, effective strategy), timestamps.
- **Table**: Location name, product type (badge), product ID (copyable), on hand, reserved, available (colored: red=0, yellow<=reorder, green), reorder point, strategy, updated_at. Filters: location, strategy, stock_status (in_stock/low_stock/out_of_stock via raw SQL). Actions: View, Edit. Bulk delete. Default sort: updated_at desc.
- **Navigation badge**: Count of items where available <= reorder_point (warning color).

### InventoryMovementResource
- **Model**: `InventoryMovement`
- **Owner scoping**: `InventoryOwnerScope::applyToMovementQuery()`.
- **Pages**: `List` → `View`. No Create, no Edit. `canCreate()` returns false.
- **Infolist**: Movement details (type badge colored by enum, quantity, date), product info, location info (from/to), additional info (reason, reference, user_id), metadata.
- **Table**: Date, type (colored badge), product type (badge), product ID, from/to location, quantity (signed with + prefix, colored), reason, ref type, user. Filters: type, from_location, to_location, date_range. Actions: View only. Default sort: created_at desc.

### InventoryAllocationResource
- **Model**: `InventoryAllocation`
- **Owner scoping**: `InventoryOwnerScope::applyToQueryByLocationRelation()` via location.
- **Pages**: `List` → `View`. No Create, no Edit. `canCreate()` returns false.
- **List page**: Has a `cleanup_expired` header action that calls `InventoryAllocation::cleanupExpired()`.
- **Infolist**: Allocation ID (copyable), quantity, status (Active/Expired), product info, location & cart ID, timing (created_at, expires_at, time remaining as human diff).
- **Table**: Created, product type, product ID, location, quantity, cart ID, expires_at (colored: red=expired, green), status badge. Filters: location, status (active/expired), expiring_soon (15 min). Actions: View, ReleaseAllocationAction. Bulk action: Release Selected (with confirmation, aggregates released quantities). Default sort: expires_at asc.
- **Navigation badge**: Count of expired allocations (red).

### InventoryBatchResource (feature-gated)
- **Model**: `InventoryBatch`
- **Owner scoping**: `InventoryOwnerScope::applyToQueryByLocationRelation()` via location.
- **Pages**: `List` → `Create` → `View` → `Edit`. Full CRUD. View has Edit header action. Edit has Delete header action.
- **Form fields**: Batch number (unique), lot number, location, status, quantities (received, on_hand, reserved), dates (manufactured, expires, received), supplier batch number, unit_cost_minor, notes.
- **Infolist**: Batch details (number, lot, status, location, supplier), product info, quantities (received, on_hand, reserved, available), dates (manufactured, received, expires with color, days left badge), cost (unit cost, total value), notes (conditional), timestamps.
- **Table**: Batch number, lot, product type, location, current/available/reserved quantities, expires (colored by urgency), status badge, created. Filters: status, location. Actions: View, Edit. Bulk delete.
- **Navigation badge**: Count of allocatable batches expiring within 30 days (warning).

### InventorySerialResource (feature-gated)
- **Model**: `InventorySerial`
- **Owner scoping**: `InventoryOwnerScope::applyToQueryByLocationRelation()` via location. Also scopes eager-loaded batch relation.
- **Pages**: `List` → `Create` → `View` → `Edit`. Full CRUD. List has Create header action. View has Edit+Delete header actions. Edit has Delete header action.
- **Form fields**: Serial number (unique), location, batch, status, condition, unit_cost_minor, warranty_expires_at, order_id, customer_id, received_at, sold_at.
- **Infolist**: Serial details (number, status/condition badges, location, batch), product info, cost & warranty (unit cost, warranty expires colored, warranty status badge), order info (conditional), dates (received/sold), timestamps.
- **Table**: Serial number (copyable), product type, location, batch, status badge, condition badge, cost (money column, divideBy:100), warranty (colored by remaining days), order, created. Filters: status, condition, location. Actions: View, Edit. Bulk delete. Default sort: created_at desc.
- **Navigation badge**: Count of serials with status=Available (green).

## 4. Actions (8 — all on-resource Filament Actions)

All actions validate the location belongs to the current owner context via `InventoryOwnerScope` before executing domain operations from the `inventory` package. Failed validation produces a danger notification.

| Action | Domain Call | Trigger | Key Fields | Validation |
|--------|-------------|---------|------------|------------|
| `ShipStockAction` | `ShipInventory::run()` | On product record | location_id, quantity, order_number, customer, tracking_number, shipped_at, notes | Location owner scope check; catches `InsufficientInventoryException` |
| `ReceiveStockAction` | `ReceiveInventory::run()` | On product record | location_id, quantity, purchase_order, supplier, received_at, notes | Location owner scope check |
| `TransferStockAction` | `TransferInventory::run()` | On product record | from_location_id, to_location_id, quantity, notes | Both locations owner scope check; validates from != to |
| `AdjustStockAction` | `AdjustInventory::run()` | On product record | location_id, new_quantity, reason (enum), notes | Location owner scope check |
| `CycleCountAction` | `AdjustInventory::run()` | On product record | location_id (live-updates system_quantity), counted_quantity, counter | Location owner scope check; auto-adjusts only on variance != 0; variance reported in note |
| `ApproveReorderSuggestionAction` | `ApproveReorderSuggestion::run()` | On `InventoryReorderSuggestion` record | None (record-based) | Checks location owner scope (handles null location via global/includeGlobal rules) |
| `RejectReorderSuggestionAction` | `RejectReorderSuggestion::run()` | On `InventoryReorderSuggestion` record | None (record-based) | Checks location owner scope (same logic as approve) |
| `ReleaseAllocationAction` | `ReleaseStock::make()->releaseAllocation()` | On `InventoryAllocation` record | None (record-based) | Handles negative result with danger notification |

## 5. Widgets (9 — all feature-flagged)

All widgets that query data use `InventoryOwnerScope` for owner-scoped data.

| Widget | Type | Sort | Data Source | Notes |
|--------|------|------|-------------|-------|
| `InventoryStatsWidget` | `StatsOverviewWidget` | 10 | `InventoryStatsAggregator::getOverviewStats()` | 6 stats: active locations, SKUs, on hand, reserved, available, low stock |
| `LowInventoryAlertsWidget` | `TableWidget` | 20 | `InventoryStatsAggregator::getLowStockQuery()` | Columns: location, type, product ID, on hand, reserved, available, reorder point, deficit. Paginated. Striped. Sorted by deficit desc. |
| `InventoryValuationWidget` | `StatsOverviewWidget` | 20 | `ValuationService::getTotalValuation()` | 4 stats: total value (formatted via MoneyFormatter), total units, unique SKUs, avg unit cost. Polls every 60s. |
| `ExpiringBatchesWidget` | `TableWidget` | 30 | `InventoryBatch::allocatable()->expiringSoon()` | Columns: batch, product type, location, available qty, expires (colored), days left (badge). Views routed to batch resource. |
| `ReorderSuggestionsWidget` | `TableWidget` | 40 | `InventoryReorderSuggestion::pending()->byUrgency()` | Columns: product type, location, current stock, reorder point, suggested qty, stockout date (colored), urgency/status badges. Inline Approve/Reject actions. |
| `BackordersWidget` | `TableWidget` | 50 | `InventoryBackorder::openStatuses()->byPriority()` | Columns: product type, order, location, requested/fulfilled/remaining qty, promised date, priority/status badges. Views routed to backorders resource. |
| `InventoryKpiWidget` | `StatsOverviewWidget` | 5 | `InventoryKpiService` | 4 stats: turnover ratio (with trend & sparkline), days on hand, fill rate (with trend & sparkline), accuracy (with sparkline). Polls every 60s. |
| `MovementTrendsChart` | `ChartWidget` (line) | 6 | `MovementAnalysisReport::getDailyMovementTrends()` | 4 datasets: receipts, shipments, adjustments, transfers. Interactive (intersect:false, mode:index). Polls every 60s. |
| `AbcAnalysisChart` | `ChartWidget` (doughnut) | 7 | `StockLevelReport::getAbcAnalysis()` | 1 dataset: SKU count by ABC class. Description shows Class A percentage. Legend on right. |

## 6. Services (1)

### InventoryStatsAggregator
- **Binding**: Singleton in `FilamentInventoryServiceProvider`.
- **Purpose**: Centralized, owner-scoped, cached stats computation for widgets.
- **Methods**:
  - `overview()`: total_locations, active_locations, total_skus, total_on_hand, total_reserved, active_allocations.
  - `movementStats(int $days=30)`: receipts, shipments, transfers, adjustments, total count.
  - `lowInventoryCount(?int $threshold)`: count of levels where (on_hand - reserved) <= threshold, at active locations.
  - `outOfStockCount()`: count of levels where (on_hand - reserved) <= 0, at active locations.
  - `getOverviewStats()`: cached — active_locations, total_skus, total_on_hand, total_reserved, total_available, low_stock_count.
  - `lowStockCount()`: cached — count where (on_hand - reserved) <= reorder_point and reorder_point > 0.
  - `getLowStockQuery()`: Eloquent Builder for the low stock table widget.
  - `clearCache()`: flushes cached overview_stats and low_stock_count with owner suffix.
- **Caching**: Uses `Cache::remember()` with configurable TTL (`filament-inventory.cache.stats_ttl`, default 60s). Cache keys include owner scope suffix via `InventoryOwnerScope::cacheKeySuffix()`. TTL ≤ 0 disables caching.
- **Distinct SKU counting**: Uses a subquery approach (SQLite-compatible) — owner-scoped distinct `(inventoryable_type, inventoryable_id)` wrapped in `fromSub()`.

## 7. Policies (3)

All policies use `FilamentPermission::hasAbility()` for every method. No policy allows unrestricted create on allocations or reorder suggestions.

| Policy | Model | Abilities |
|--------|-------|-----------|
| `InventoryLevelPolicy` | `InventoryLevel` | `viewAny`, `view`, `create`, `update`, `delete` — each gated by `inventory-level.*` ability |
| `InventoryAllocationPolicy` | `InventoryAllocation` | `viewAny`, `view`, `update`, `delete` — `create` returns false; each gated by `inventory-allocation.*` ability |
| `InventoryReorderSuggestionPolicy` | `InventoryReorderSuggestion` | `viewAny`, `view`, `update`, `delete` — `create` returns false; each gated by `inventory-reorder-suggestion.*` ability |

## Config Contract

```php
'navigation_group'          => 'Inventory',
'tables.expiry_warning_days' => 30,
'defaults.costing_method'    => 'fifo',
'features.stats_widget'              => true,   // env
'features.low_stock_widget'          => true,   // env
'features.expiring_batches_widget'   => true,   // env
'features.reorder_suggestions_widget' => true,  // env
'features.backorders_widget'         => true,   // env
'features.valuation_widget'          => true,   // env
'features.kpi_widget'                => true,   // env
'features.movement_trends_chart'     => true,   // env
'features.abc_analysis_chart'        => true,   // env
'features.batch_resource'            => true,   // env
'features.serial_resource'           => true,   // env
'resources.navigation_sort.locations'   => 10,
'resources.navigation_sort.levels'      => 20,
'resources.navigation_sort.movements'   => 30,
'resources.navigation_sort.allocations' => 40,
'resources.navigation_sort.batches'     => 50,
'resources.navigation_sort.serials'     => 60,
'cache.stats_ttl'                    => 60,    // env
```
