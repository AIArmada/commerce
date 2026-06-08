# Inventory friendliness review

This note reviews `packages/inventory` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Services` (17 classes)
- `src/Actions` (5 classes)
- `src/Console/Commands`
- `src/Listeners` (5 classes)
- `src/Strategies` (3 classes)
- `src/Models` (14 classes)
- `src/Exports` (5 classes)
- `src/Reports`
- `src/Cart` (cart integration)
- `src/Integrations` (checkout, shipping, fulfillment adapters)
- downstream consumers in `cart`, `checkout`, `orders`, `shipping`

## What is already friendly

### Allocation strategies are real variants

- `Strategies/FefoStrategy.php`
- `Strategies/NearestLocationStrategy.php`
- `Strategies/AllocationStrategyInterface.php`
- `Strategies/AllocationContext.php`

This is the right shape. Each allocation algorithm is its own class behind a contract.

### Cart integration is isolated

- `Cart/CartManagerWithInventory.php`
- `Cart/InventoryValidator.php`
- `Cart/ValidateInventoryOnAdd.php`

The cart package composes inventory through a wrapper, not by reaching into inventory models. This is the right boundary.

### Checkout and shipping integrations are explicit

- `Integrations/CheckoutInventoryService.php` (impl `Contracts/CheckoutInventoryServiceInterface.php`)
- `Integrations/FulfillmentLocationService.php` (plugs into shipping)

Cross-package integration is handled through contracts, not by editing the consumer.

### Costing methods are real variants

- `Services/FifoCostService.php`
- `Services/WeightedAverageCostService.php`
- `Services/StandardCostService.php`
- `Services/ValuationService.php`

Each costing method is a separate service. Adding a new method is additive.

### Listeners react to orders and cart events

- `Listeners/CommitInventoryOnPayment.php`
- `Listeners/DeductInventoryFromOrder.php`
- `Listeners/ReleaseInventoryFromOrder.php`
- `Listeners/ReleaseInventoryOnCartClear.php`
- `Listeners/ReserveStockOnCheckout.php`

Cross-package reactions go through events, not direct calls.

## Findings

### 1. Service count is high (17) and many overlap in responsibility

**Files in `src/Services/`**

- `InventoryService` (likely catch-all facade)
- `InventoryAllocationService`, `BatchAllocationService`, `BatchService`
- `BackorderService`
- `SerialService`, `SerialLookupService`
- `FifoCostService`, `WeightedAverageCostService`, `StandardCostService`, `ValuationService`
- `DemandForecastService`, `ReplenishmentService`
- `ExpiryMonitorService`, `StockThresholdService`, `LocationTreeService`
- `AlertDispatchService`

**Why this hurts friendliness**

There are too many services. Some are clearly variant families (costing methods) but others (Batch vs Allocation, ExpiryMonitor vs StockThreshold, Replenishment vs DemandForecast) overlap or share orchestration. Callers have to know which one to use.

**Recommendation**

Group services into focused domains:

- `Services/Stock/` (allocation, backorder, threshold, replenishment)
- `Services/Batch/` (batch lifecycle, expiry)
- `Services/Serial/` (serial lifecycle, lookup)
- `Services/Costing/` (FIFO, weighted, standard, valuation)
- `Services/InventoryService.php` stays as a thin facade

The 17-file flat structure makes it hard to see the boundaries.

### 2. Action count is low (5) given the service count

**Files in `src/Actions/`**

- `AdjustInventory`
- `ReceiveInventory`
- `ShipInventory`
- `TransferInventory`
- `CheckLowInventory`

**Why this hurts friendliness**

Mutations are split between services and Actions. Some lifecycle operations (batch creation, allocation, valuation snapshot) likely live in services rather than Actions. This is inconsistent with the monorepo's "Actions only, no logic in services" rule.

**Recommendation**

Move all inventory mutations into Actions:

- `Actions/AllocateStock`, `Actions/ReleaseStock`, `Actions/CommitStock`
- `Actions/CreateBatch`, `Actions/ExpireBatch`, `Actions/RecallBatch`
- `Actions/RecordSerial`, `Actions/TrackSerial`
- `Actions/CreateValuationSnapshot`
- `Actions/Backorder`, `Actions/ResolveBackorder`

Services become read-side (queries, threshold checks, forecasts) or thin orchestrators.

### 3. Two allocation strategy classes but only one looks complete

**Files**

- `Strategies/FefoStrategy.php`
- `Strategies/NearestLocationStrategy.php`

**Why this hurts friendliness**

FEFO (First Expiry First Out) and nearest-location are real variants, but the package may not have a registry or contract for registering new strategies. New strategies (FIFO, LIFO, balanced, manual override) may have to be added in the same place as the existing ones.

**Recommendation**

Add a `Strategies/AllocationStrategyRegistry` (or tagged binding) so new strategies can be registered without editing the orchestrator. The allocation service resolves the configured strategy by name from the registry.

### 4. Costing methods are siblings without a clear contract

**Files**

- `Services/FifoCostService.php`
- `Services/WeightedAverageCostService.php`
- `Services/StandardCostService.php`

**Why this hurts friendliness**

These look like variants, but they may not implement a common contract. Switching costing method requires editing the valuation service rather than swapping an adapter.

**Recommendation**

Add `Contracts/CostingMethodInterface` and have all three implement it. Add a `CostingMethodRegistry` to switch at runtime.

### 5. `InventoryService` is a likely catch-all facade

**Files**

- `src/Services/InventoryService.php`

**Why this hurts friendliness**

Like `OrderService`, this is probably a single class that owns many operations. Every caller (cart, checkout, orders, filament-inventory) goes through it.

**Recommendation**

Audit `InventoryService` for opportunity to delegate to Actions. Keep it as a compatibility facade but move mutations to the new Action tree.

### 6. Console commands exist but the orchestration likely duplicates owner-loop logic

**Files**

- `src/Console/CleanupExpiredAllocationsCommand.php`
- `src/Console/CreateValuationSnapshotCommand.php`

**Why this hurts friendliness**

These commands probably iterate over all owners and run per-owner work. This is the same pattern as the affiliates and signals commands.

**Recommendation**

Use `commerce-support`'s `OwnerBatchRunner` (when it lands) for both commands. The monorepo's foundation should own this pattern.

### 7. Reports and exports have separate service classes

**Files**

- `src/Reports/InventoryKpiService.php`
- `src/Reports/StockLevelReport.php`
- `src/Reports/MovementAnalysisReport.php`
- `src/Exports/ExportService.php`
- `src/Exports/StockLevelExport.php`
- `src/Exports/MovementExport.php`
- `src/Exports/BatchExport.php`
- `src/Exports/ValuationExport.php`

**Why this hurts friendliness**

Reports and exports are siblings but with similar patterns (filter, query, format). A new report type means another class with similar boilerplate.

**Recommendation**

Add a `Reports/ReportInterface` and `Exports/ExportInterface`. Reports and exports register themselves, and a registry resolves the right one for a given request.

## Concrete refactor plan

### Phase 1 — group services by domain

**Steps**

1. Create `Services/Stock/`, `Services/Batch/`, `Services/Serial/`, `Services/Costing/` subfolders.
2. Move related services.
3. Make `InventoryService` a thin facade.

### Phase 2 — extract mutations to Actions

**Steps**

1. Move all inventory mutations from services to Actions.
2. Update callers (listeners, console commands, controllers, integrations).
3. Add tests for each new Action.

### Phase 3 — add contracts and registries for strategies and costing

**Steps**

1. Add `Contracts/CostingMethodInterface` and a registry.
2. Add an allocation strategy registry.
3. Update `InventoryService` and `ValuationService` to use the registries.

### Phase 4 — adopt owner-batch helper for console commands

**Steps**

1. Wait for `commerce-support`'s `OwnerBatchRunner`.
2. Migrate `CleanupExpiredAllocationsCommand` and `CreateValuationSnapshotCommand`.
3. Add characterization tests first.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — group services by domain

- [pending] Create `Services/Stock/`, `Services/Batch/`, `Services/Serial/`, `Services/Costing/` subfolders.
- [pending] Move related services.
- [pending] Make `InventoryService` a thin facade.

### Phase 2 — extract mutations to Actions

- [pending] Move all inventory mutations from services to Actions.
- [pending] Update callers (listeners, console commands, controllers, integrations).
- [pending] Add tests for each new Action.

### Phase 3 — add contracts and registries for strategies and costing

- [pending] Add `Contracts/CostingMethodInterface` and a registry.
- [pending] Add an allocation strategy registry.
- [pending] Update `InventoryService` and `ValuationService` to use the registries.

### Phase 4 — adopt owner-batch helper for console commands

- [pending] Wait for `commerce-support`'s `OwnerBatchRunner`.
- [pending] Migrate `CleanupExpiredAllocationsCommand` and `CreateValuationSnapshotCommand`.
- [pending] Add characterization tests first.



## Suggested verification scope

- per-Action tests for new mutation Actions
- allocation strategy tests
- costing method tests
- cross-package tests for cart/checkout/orders after refactor
- inventory listener tests (the most likely regression site)

## Recommended first move

Phase 2 — extract mutations to Actions. The 17-service count and 5-Action count is the most visible sign of inconsistency. The split is mostly mechanical and unblocks later strategy refactors.
