---
title: Filament Events Context
package: filament-events
status: current
surface: filament
family: analytics-and-events
keywords:
  - filament
  - events-ui
  - check-in
---

# Filament Events Context

## Snapshot
- Composer: `aiarmada/filament-events`
- Role: Filament admin for events/occurrences/sessions/venues/registrations + check-in console.
- Triggers: filament, events-ui, check-in
- Search first: `src/Resources, src/Pages, config, docs`
- Related: `events`, `filament-authz`, `filament-ticketing`
- Paired: `events` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../events/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `events`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `events` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Event operations UI.
- Skip when: Registration rules — see events; ticket CRUD — see filament-ticketing.
- Owner/security: Owner queries; global preview surfaces intentional.

## Key surfaces
- Resources: `EventAttendanceResource`, `EventChangeLogResource`, `EventOccurrenceResource`, `EventRegistrationParticipantResource`, `EventRegistrationResource`, `EventResource`, `EventSessionResource`, `EventTaxonomyResource`, `EventTemplateResource`, `EventTermResource`
- Actions/Services: `Actions/Exporter/EventAttendanceExporter`, `Actions/Exporter/EventExporter`, `Actions/Exporter/EventOccurrenceExporter`, `Actions/Exporter/EventRegistrationExporter`, `Actions/Exporter/EventSessionExporter`, `Actions/Exporter/VenueExporter`, `Actions/Importer/EventRegistrationImporter`, `Actions/Importer/EventSessionImporter`
- Config `filament-events.php`: `navigation`, `group`, `resources`, `enabled`, `event`, `occurrence`, `session`, `venue`, `venue_space`, `registration`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
