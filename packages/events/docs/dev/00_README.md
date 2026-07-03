# Events Package Developer Notes

This developer pack has been reduced to current-state routing notes.

The event package no longer owns ticket or seating persistence. Event scheduling, registrations, check-in, attendance, and event-scoped orchestration stay in `aiarmada/events`. Generic ticket, pass, and seat primitives live in sibling packages.

## Read next

1. `../../CONTEXT.md`
2. `../../docs/01-overview.md`
3. `../../docs/04-usage.md`
4. `../../../ticketing/CONTEXT.md`
5. `../../../seating/CONTEXT.md`
6. `../../../filament-events/CONTEXT.md`
7. `../../../filament-ticketing/CONTEXT.md`
8. `07_IMPLEMENTATION_PHASES.md`
