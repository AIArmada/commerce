## AIArmada Inventory

Use Inventory actions for stock writes and the reservation contracts for checkout. Scoped resource capacity is owned by the ScopedCapacity feature; consume its public `ScopedCapacityReservationServiceInterface` rather than private persistence or model classes. Provide an explicit owner/global context when tenancy is enabled. Do not write reserved quantities directly.

Cart allocations are independent of checkout reservation groups. Use the checkout reservation contract for grouped lifecycle operations, not cart-reference mutations.

Allocation actions (`AllocateStock`, `CommitStock`, `ReleaseStock`) live under `AIArmada\Inventory\Features\Reservations\Actions`.

Stock levels, locations and movement ledger models live under `AIArmada\Inventory\Features\Stock\Models`.

Stock threshold evaluation, alert persistence and location hierarchy services belong to `Features/Stock/Persistence`; use the feature-owned classes directly.

Inventory reporting and reconciliation queries live under `AIArmada\Inventory\Features\Reporting\Queries`.

Use `AIArmada\Inventory\Features\Stock\Queries\FulfillmentLocationService` for read-only fulfillment location planning.

Inventory exports use `Contracts/ExportableInterface`, `Features/Reporting/Queries` and `Features/Reporting/Actions/ExportService`.

Use the immutable `AIArmada\Inventory\Data\AllocationContext` for allocation strategy inputs. Fluent context methods return new instances.

FEFO batch planning belongs to `Features/Traceability/Queries/FefoStrategy`; nearest-location planning belongs to `Features/Stock/Queries/NearestLocationStrategy`. Resolve FEFO through the container for the stock visibility dependency. These planners are read-only and do not reserve inventory.

Reorder suggestion reporting uses `Features/Replenishment/Api/ReorderSuggestionCounts`; implement `Contracts/ExportableInterface` for custom export rows.

Dashboard and location stock values represent recorded remaining cost layers in `inventory.defaults.currency`, defaulting to FIFO. Pass `CostingMethod::WeightedAverage` for weighted-average layers. Standard costing values physical on-hand balances at the effective standard cost in the configured currency. Uncosted stock is not included in monetary totals.

ABC analysis ranks positive monetary inventory values, not physical quantities. It uses the full valued inventory denominator even when the displayed result is limited. A SKU crossing a class threshold remains in the class where its cumulative contribution began.

Stagnant-stock reporting returns recorded inventory value for the exact SKU/location and `null` when no matching cost is recorded. Reorder value uses linked supplier order costs in the reporting currency for pending/approved suggestions, with an explicit unpriced count. Ordered suggestions are excluded.

Cycle counts are recorded through `AdjustInventory` with canonical reason `cycle_count`, including accurate zero-variance counts. Adjustment movements record locked before/after quantities. Cycle-count monetary variance is unavailable (`null`) for nonzero variances without historical cost evidence; percentage is unavailable if any count lacks a meaningful baseline.

Use `Features/Valuation/Api/InventoryValues` for reporting across FIFO, weighted-average and standard costing. Standard-cost lookup matches exact stock owner and item identities, with no cross-owner fallback.

Serial-number tracking uses `Features/Traceability/Traits/HasSerialNumbers`. Single and bulk registration use the Traceability persistence boundary and record registration history atomically.

Serial lifecycle mutations belong to `AIArmada\Inventory\Features\Traceability\Persistence\SerialService`. Use `AIArmada\Inventory\Features\Traceability\Queries\SerialLookupService` for `findBySerialNumber`, `getForModel`, `getAvailableForSale`, and `getHistory`; do not query through the mutation service.

Use `AIArmada\Inventory\Features\Traceability\Queries\BatchLookupService` for batch lookup, allocatable batches and total available quantities; use `ExpiryMonitorService` for expiry queries and `Actions\ProcessExpiredBatches` for expiration mutations. `BatchService` owns batch lifecycle writes.

Composite cart and order stock demand belongs to `AIArmada\Inventory\Features\Reservations\Queries\InventoryDemandResolver`. The Reservations query resolves frozen component snapshots and aggregates demand by actual inventory identity before allocating or deducting stock.

The Stock feature owns the public inventory service registration. Model read helpers call `InventoryStockQuery` directly. Default-location receipt and shipment use `Features/Stock/Actions/ReceiveAtDefault` and `ShipFromDefault` so location initialization and stock changes are atomic.

Use the pure `AIArmada\Inventory\Data\AllocationStrategy` enum when selecting allocation strategies across the stock and reservation boundaries.

Read per-location sellable quantity through the canonical `InventoryLevel::$available` attribute. Aggregate availability by clamping each location's on-hand minus reserved quantity to zero before summing.

Persisted inventory level identity (inventoryable type, inventoryable ID and location ID) is immutable. Use `TransferInventory` to move stock between locations rather than reassigning an existing level.

The Backorders feature owns its model states (`AIArmada\Inventory\Features\Backorders\States`). Register the stock health entry point `AIArmada\Inventory\Features\Stock\EntryPoints\Health\LowStockCheck` for active-location sellable stock checks.
