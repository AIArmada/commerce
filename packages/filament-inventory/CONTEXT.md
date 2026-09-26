---
title: Filament Inventory Context
package: filament-inventory
status: current
surface: filament
family: catalog-and-identity
keywords:
  - filament
  - stock-ui
  - transfer
  - cycle-count
---

# Filament Inventory Context

## Snapshot
- Composer: `aiarmada/filament-inventory`
- Role: Filament inventory ops: locations/levels/movements/allocations/batches/serials + transfer/receive/ship actions.
- Triggers: filament, stock-ui, transfer, cycle-count
- Search first: `src/Resources, src/Widgets, config, docs`
- Related: `inventory`, `filament-authz`
- Paired: `inventory` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../inventory/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `inventory`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `inventory` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Inventory operations UI.
- Skip when: Allocation/costing math — see inventory.
- Owner/security: InventoryOwnerScope + revalidation.

## Key surfaces
- Resources: `InventoryLocationResource`, `InventoryLevelResource`, `InventoryMovementResource`, `InventoryAllocationResource`, `InventoryBatchResource` (feature-flagged), `InventorySerialResource` (feature-flagged)
- Policies: `InventoryLevelPolicy`, `InventoryAllocationPolicy`, `InventoryReorderSuggestionPolicy`
- Widgets: `InventoryStatsWidget`, `LowInventoryAlertsWidget`, `ExpiringBatchesWidget`, `ReorderSuggestionsWidget`, `BackordersWidget`, `InventoryValuationWidget`, `InventoryKpiWidget`, `MovementTrendsChart`, `AbcAnalysisChart`
- Actions/Support: `Actions/{ReceiveStockAction,ShipStockAction,TransferStockAction,AdjustStockAction,CycleCountAction,ReleaseAllocationAction,ApproveReorderSuggestionAction,RejectReorderSuggestionAction}`, `Services/InventoryStatsAggregator` (`clearCache()`)
- Config `filament-inventory.php` keys: `navigation.group`, `tables.expiry_warning_days`, `defaults.costing_method`, `features.{stats_widget,low_stock_widget,expiring_batches_widget,reorder_suggestions_widget,backorders_widget,valuation_widget,kpi_widget,movement_trends_chart,abc_analysis_chart,batch_resource,serial_resource}`, `resources.navigation_sort.{locations,levels,movements,allocations,batches,serials}`, `cache.stats_ttl`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-widgets.md`, `06-actions.md`
