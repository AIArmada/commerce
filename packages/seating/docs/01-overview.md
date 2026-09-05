---
title: Seating Overview
---

# Seating

The `aiarmada/seating` package provides venue seat layout modeling, seat hold and allocation management, and a Livewire seat-map rendering component. It is vendor-agnostic: any Eloquent model (event, venue, etc.) can own a seat map via a polymorphic `seatable` relationship.

## Features

- **Seat Maps** — polymorphic layouts with versioning
- **Sections** — logical grouping of seats (VIP, Standard, etc.)
- **Seats** — individual seats with row/column positioning, status, and category
- **Holds** — temporary time-bounded holds (cart reservations, checkout flow)
- **Allocations** — persistent seat-to-entity assignments (passes, registrations)
- **Livewire Component** — interactive seat picking with status visualization
- **Console Command** — `seating:release-expired-holds` to clean up stale holds
- **Seating Modes** — `none`, `general_admission`, `assigned`, `hybrid` via `SeatingMode` enum

## What this package owns

- Models `SeatMap`, `SeatSection`, `Seat`, `SeatHold`, `SeatAllocation` (all owner-scoped via `seating.owner`)
- Actions `ResolveSeatMapForHostAction`, `EnsureSeatHoldAction`, `EnsureSectionAllocationAction`, `ConvertHoldsToAllocationsAction`, `ReleaseAllocationsAction`
- `SeatAllocatorInterface` contract with `DefaultSeatAllocator` and `NullSeatAllocator`, plus `SeatLayoutRenderer`
- Config `seating.php`: `database`, `holds` (TTL), `owner`, `modes`, `scheduling`

## What this package does not own

- Ticket issuance, passes, or transfers — see `aiarmada/ticketing`
- Event scheduling or registrations — see `aiarmada/events`
- Admin UI — see `aiarmada/filament-seating` (`SeatMapResource`, editor + occupancy pages, overview widget)
