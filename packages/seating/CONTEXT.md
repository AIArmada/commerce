---
title: Seating Context
package: seating
status: current
surface: domain
family: venue
---

# Seating Context

## Snapshot
- Composer: `aiarmada/seating`
- Role: Venue seat layout (maps, sections, seats, holds, allocations) and the shared seat-allocation contract. Vendor-agnostic — owns no domain-specific data.
- Search first: `src/Models`, `src/Contracts`, `src/Services`, `src/Livewire`, `database/migrations`, `config`, `docs`
- Related: `ticketing`, `events`, `filament-seating`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../ticketing/CONTEXT.md` when allocation flow is involved
6. `../events/CONTEXT.md` when event-scoped seat maps are involved
7. `docs/02-installation.md` when setup or publishing changes

## Guardrails
- Owns seat layout (Seat, SeatMap, SeatSection, SeatHold, SeatAllocation), the SeatAllocatorInterface contract, the default allocator, and the Livewire SeatMap component.
- Does NOT own passes, ticket types, registrations, or events. Cross-package linking is via polymorphic `seatable_type`/`seatable_id` on the layout tables.
- Vendor-agnostic. Domain packages (events, ticketing, future bookings/classes) provide the polymorphic host via their own models.
- Update `docs/*.md` in the same pass when public behavior or config changes.
