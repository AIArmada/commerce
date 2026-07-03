# Filament Admin Notes

The event admin package is now focused on event workflows.

## Current admin split

- `aiarmada/filament-events` owns event, occurrence, session, venue, registration, attendance, check-in, approval, and notification surfaces.
- `aiarmada/filament-ticketing` owns ticket type and pass administration.
- `aiarmada/filament-seating` owns seating administration when that package is installed.

Event-facing pages may display related ticket or seat information, but CRUD for those domains should stay in their owning Filament packages.

## Read next

1. `../../../filament-events/docs/01-overview.md`
2. `../../../filament-events/docs/04-usage.md`
3. `../../../filament-ticketing/docs/01-overview.md`
4. `../../../filament-seating/CONTEXT.md`
