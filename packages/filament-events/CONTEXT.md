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
- Role: Filament v5 admin adapter for `aiarmada/events` — resources, relation managers, check-in console, approval queue, public preview, and stats widget.
- Triggers: filament, events-ui, check-in
- Search first: `src/Resources, src/Pages, src/RelationManagers, config, docs`
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
- All mutations go through `events` scalar-ID contracts: `EventLifecycleWorkflow` (`publish($eventId)`, `cancel('occurrence', $id, …)`), `EventCloneService`, `EventModerationWorkflow`, `EventCheckInService`, `EventTemplateService` — never mutate status columns directly.
- Resolve event models through `AIArmada\Events\Platform\ModelResolver`; venue and taxonomy resources are global catalogs (no owner scope) while event-bound resources apply `OwnerUiScope::apply(..., includeGlobal: false)`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `events` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Event operations UI.
- Skip when: Registration rules — see events; ticket CRUD — see filament-ticketing.
- Owner/security: Owner queries for event-bound resources; venue/taxonomy catalogs and global preview surfaces are intentional.

## Key surfaces
- Resources: `EventResource`, `EventOccurrenceResource`, `EventSessionResource`, `EventAttendanceResource`, `EventChangeLogResource`, `EventRegistrationResource`, `EventRegistrationParticipantResource`, `EventTemplateResource`, `EventTaxonomyResource`, `EventTermResource`, `VenueResource`, `VenueSpaceResource`
- Pages: `CheckInConsole`, `ApprovalQueue`, `EventPublicPreview`
- Widgets: `EventStatsWidget`; host embedding: `RelationManagers\EventsRelationManager`
- Extensions: `Contracts\EventFormExtension`, `Contracts\EventOccurrenceFormExtension`, `Contracts\TermFormExtension`; default `Extensions\DefaultEventMediaExtension`
- Import/Export: `Actions\Importer\{EventRegistrationImporter,EventSessionImporter,VenueImporter}`, `Actions\Exporter\{EventAttendanceExporter,EventExporter,EventOccurrenceExporter,EventRegistrationExporter,EventSessionExporter,VenueExporter}`
- Config `filament-events.php`: `navigation.group`; `resources.enabled`, `resources.navigation_sort`, `resources.navigation_group`, `resources.event_form_extensions`, `resources.occurrence_form_extensions`, `resources.term_form_extensions`, `resources.*_relation_managers`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
