---
name: seating-development
description: Develop and integrate seating using owner-safe domain operations.
---

# Seating development

Seating owns seat maps, sections, seats, holds and allocations. Use EnsureSeatHoldAction for hold creation, ConvertHoldsToAllocationsAction for conversion, EnsureSectionAllocationAction for shared-capacity allocation, and ReleaseAllocationsAction for release. Cross-feature callers use MapsApi and ReservationsApi; the allocator and Livewire component delegate through those APIs. Always preserve owner scoping, deterministic lock order, and owner-scoped layout caching. Livewire selections are not reservations.

## Verification

Run the package architecture checks, affected monorepo Pest tests, PHPStan and Pint. Update callers directly; do not introduce compatibility aliases or backfill migrations.
