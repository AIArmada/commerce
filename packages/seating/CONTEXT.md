---
title: Seating Context
package: seating
status: current
surface: domain
family: venue
keywords:
  - seat
  - seat-map
  - hold
  - allocation
  - livewire
---

# Seating Context

## Snapshot
- Composer: `aiarmada/seating`
- Role: Vendor-agnostic seat maps/holds/allocations + Livewire picker + allocator contract.
- Triggers: seat, seat-map, hold, allocation, livewire
- Search first: `src/Models, src/Actions, src/Services, config, docs`
- Related: `filament-seating`, `ticketing`, `events`
- Paired: `filament-seating` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-seating/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-seating`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Seat selection, holds, allocations.
- Skip when: Ticket issuance — see ticketing.
- Owner/security: Owner-scoped (all 5; seating.owner).

## Key surfaces
- Models: `Seat`, `SeatAllocation`, `SeatHold`, `SeatMap`, `SeatSection`
- Actions/Services: `Actions/ConvertHoldsToAllocationsAction`, `Actions/EnsureSeatHoldAction`, `Actions/EnsureSectionAllocationAction`, `Actions/ReleaseAllocationsAction`, `Actions/ResolveSeatMapForHostAction`, `Services/DefaultSeatAllocator`, `Services/NullSeatAllocator`, `Services/SeatLayoutRenderer`
- Config `seating.php`: `database`, `json_column_type`, `tables`, `seat_maps`, `seat_sections`, `seats`, `seat_holds`, `seat_allocations`, `holds`, `ttl_minutes`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
