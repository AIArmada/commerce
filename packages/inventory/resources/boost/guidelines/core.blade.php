## AIArmada Inventory

Use Inventory actions for stock writes and the reservation contracts for checkout. Scoped resource capacity is owned by the ScopedCapacity feature; consume its public `ScopedCapacityReservationServiceInterface` rather than private persistence or model classes. Provide an explicit owner/global context when tenancy is enabled. Do not write reserved quantities directly.

Cart allocations are independent of checkout reservation groups. Use the checkout reservation contract for grouped lifecycle operations, not cart-reference mutations.
