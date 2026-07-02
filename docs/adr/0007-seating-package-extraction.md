# ADR 0007: Seating Package Extraction

## Status
Accepted

## Context
Ticketing has a `SeatAllocatorInterface` contract and `NullSeatAllocator` implementation, but seating concepts (seat maps, sections, holds, allocations) are growing beyond a simple contract. The events package also needs seating. Rather than putting seating in ticketing (creating a circular dependency with events), extract seating as a peer package.

## Decision
Create `aiarmada/seating` as a standalone package containing:
- Seat layout models (SeatMap, SeatSection, Seat, SeatHold, SeatAllocation)
- SeatAllocatorInterface (moved from ticketing)
- DefaultSeatAllocator and NullSeatAllocator (NullSeatAllocator moved from ticketing)
- SeatLayoutRenderer and SeatLayoutInterface
- Livewire SeatMap component (storefront-facing)
- Console command for releasing expired holds

Create `aiarmada/filament-seating` as the Filament admin adapter with:
- SeatMapResource (CRUD)
- SeatMapEditor and SeatMapOccupancy pages (disabled by default)
- SeatMapOverview dashboard widget

Dependency direction: `seating` → `commerce-support`; `ticketing` → `commerce-support` + `seating`.

## Consequences
- Ticketing and events can both depend on seating without circular deps
- Ticketing's `SeatAllocatorInterface` was removed; `TicketingServiceProvider` binds to seating's version
- No backward-compat aliases — hard cut
- Generic column names on `passes` (`registration_type/id`, `occurrence_id`, `session_id`) keep ticketing vendor-agnostic
- Polymorphic `seatable` on seat maps allows any model to own a layout
- All models use `HasOwner` for tenant scoping
- No database cascades; cascades enforced in application logic
