## AIArmada Inventory

Use Inventory actions for stock writes and the reservation contracts for checkout. Scoped resource capacity is owned by the ScopedCapacity feature; consume its public `ScopedCapacityReservationServiceInterface` rather than private persistence or model classes. Provide an explicit owner/global context when tenancy is enabled. Do not write reserved quantities directly.

Cart allocations are independent of checkout reservation groups. Use the checkout reservation contract for grouped lifecycle operations, not cart-reference mutations.

Allocation actions (`AllocateStock`, `CommitStock`, `ReleaseStock`) live under `AIArmada\Inventory\Features\Reservations\Actions`.

Stock levels, locations and movement ledger models live under `AIArmada\Inventory\Features\Stock\Models`.

Stock threshold evaluation, alert persistence and location hierarchy services belong to `Features/Stock/Persistence`; use the feature-owned classes directly.

Inventory reporting and reconciliation queries live under `AIArmada\Inventory\Features\Reporting\Queries`.

Use `AIArmada\Inventory\Features\Stock\Queries\FulfillmentLocationService` for read-only fulfillment location planning.
