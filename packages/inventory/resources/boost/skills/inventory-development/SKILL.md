---
name: inventory-development
description: Implement stock movements and scoped capacity using Inventory domain boundaries.
---

# Inventory

Use named stock actions for receiving, shipping, transfer and adjustment. Use the checkout reservation contract with immutable request/result DTOs. Use `ScopedCapacityReservationServiceInterface` for non-warehouse capacity and never access feature-private capacity models. Resolve explicit owner context before mutations. Verify stock and reservation invariants with Pest and package architecture checks.

Serial lifecycle mutations belong to `AIArmada\Inventory\Features\Traceability\Persistence\SerialService`. Use `AIArmada\Inventory\Features\Traceability\Queries\SerialLookupService` for `findBySerialNumber`, `getForModel`, `getAvailableForSale`, and `getHistory`; do not query through the mutation service.

Use `AIArmada\Inventory\Features\Traceability\Queries\BatchLookupService` for batch lookup, allocatable batches and total available quantities; use `ExpiryMonitorService` for expiry queries and `Actions\ProcessExpiredBatches` for expiration mutations. `BatchService` owns batch lifecycle writes.
