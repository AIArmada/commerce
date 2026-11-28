# Seating

Seating owns seat maps, sections, seats, holds and allocations. Use `EnsureSeatHoldAction` for hold creation, `ConvertHoldsToAllocationsAction` for conversion, `EnsureSectionAllocationAction` for shared-capacity allocation, and `ReleaseAllocationsAction` for release. Cross-feature callers use `MapsApi` and `ReservationsApi`; the allocator and Livewire component delegate through those APIs. Preserve owner scoping, deterministic lock order, and owner-scoped layout caching. Livewire selections are not reservations.
