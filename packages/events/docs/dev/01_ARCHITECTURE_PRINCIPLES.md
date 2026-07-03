# Architecture Principles

## Current boundary

- `aiarmada/events` owns event definitions, occurrences, sessions, venues, registrations, check-in, attendance, lifecycle workflows, and event-scoped orchestration.
- `aiarmada/ticketing` owns reusable ticket types, pricing components, bundle products, passes, holders, transfers, and issuance primitives.
- `aiarmada/seating` owns seat maps, sections, seats, holds, and allocations.
- `aiarmada/filament-events` owns event admin workflows.
- `aiarmada/filament-ticketing` owns ticket and pass administration.

## Integration rule

Events may relate to ticketing and seating models through polymorphic relations and orchestration actions, but they must not recreate those domains locally.

## Read next

1. `../../docs/01-overview.md`
2. `../../../ticketing/docs/01-overview.md`
3. `../../../seating/docs/01-overview.md`
