---
name: inventory-development
description: Implement stock movements and scoped capacity using Inventory domain boundaries.
---

# Inventory

Use named stock actions for receiving, shipping, transfer and adjustment. Use the checkout reservation contract with immutable request/result DTOs. Use `ScopedCapacityReservationServiceInterface` for non-warehouse capacity and never access feature-private capacity models. Resolve explicit owner context before mutations. Verify stock and reservation invariants with Pest and package architecture checks.

Serial lifecycle mutations belong to `AIArmada\Inventory\Features\Traceability\Persistence\SerialService`. Use `AIArmada\Inventory\Features\Traceability\Queries\SerialLookupService` for `findBySerialNumber`, `getForModel`, `getAvailableForSale`, and `getHistory`; do not query through the mutation service.

Use `AIArmada\Inventory\Features\Traceability\Queries\BatchLookupService` for batch lookup, allocatable batches and total available quantities; use `ExpiryMonitorService` for expiry queries and `Actions\ProcessExpiredBatches` for expiration mutations. `BatchService` owns batch lifecycle writes.

A located serial must match its batch's inventory identity and location. Batch transfers cannot strand located serials, and serial transfers and returns must retain the batch location. Do not implicitly reassign serials or discard traceability links.

Composite cart and order stock demand belongs to `AIArmada\Inventory\Features\Reservations\Queries\InventoryDemandResolver`. The feature resolves frozen component snapshots and aggregates demand by actual inventory identity before allocating or deducting stock.

Use the pure `AIArmada\Inventory\Data\AllocationStrategy` enum when selecting allocation strategies across the stock and reservation boundaries.
