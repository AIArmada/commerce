---
name: filament-seating-development
description: Develop and integrate filament-seating with owner-safe Filament operations.
---

# FilamentSeating development

Register FilamentSeatingPlugin in the panel. Use SeatMapResource and SeatMapsRelationManager for map administration. Keep owner scoping and permission checks on every mutation. SeatMapEditor and SeatMapOccupancy are disabled by default; do not expose them without authorization. Use Seating actions for holds and allocations.

## Verification

Run the package architecture checks and affected monorepo Pest tests, PHPStan and Pint. Update callers directly; do not introduce compatibility aliases or backfill migrations.
