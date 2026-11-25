---
title: Events Context
package: events
status: current
surface: domain
family: analytics-and-events
keywords:
  - event
  - venue
  - occurrence
  - registration
  - check-in
  - waitlist
---

# Events Context

## Snapshot
- Composer: `aiarmada/events`
- Role: Modular Dune events domain: 9 features (`Catalog`, `Venues`, `Taxonomy`, `Registrations`, `Attendance`, `Commerce`, `Changes`, `Governance`, `Search`), pure `Contracts`/`Data`, external `Drivers`, and shared `Platform` primitives.
- Triggers: event, venue, occurrence, registration, check-in, waitlist
- Search first: `src/Features, src/Contracts, src/Data, src/Drivers, src/Platform, config, docs`
- Related: `filament-events`, `ticketing`, `seating`, `products`, `customers`, `orders`
- Paired: `filament-events` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-events/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Modular Dune layout: every durable `table:*` resource has exactly one owning feature listed in `src/Features/<Feature>/feature.json`; only that feature's `Actions`/`Persistence` classes may write it.
- Public surface is scalar-ID based: `Contracts` interfaces (`RegistrationServiceInterface`, `EventLifecycleWorkflow`, `EventCheckInService`, …), feature `Api` contracts (`CatalogApi`, `RegistrationsApi`, `AttendanceApi`, `ChangesApi`, `GovernanceApi`), and immutable `Data` DTOs/enums. Never return Eloquent models from `Api`/`Contracts`.
- `src/Platform` owns `ModelResolver`, `ExtensionRegistry` (slots `events.cascade-delete`, `events.clone-scope`), `EventWriteGuard`, `FeatureCatalog`, and the ownership scoping traits; `Platform` must never import concrete `Features` or `Drivers`.
- `EventOrganizer` is an event-scoped organizer role/profile, not a tenant organization or a replacement for shared `persons.Person`.
- Keep `composer arch:check` (AST boundaries + Larastan `OwnedWriteRule`) green; `tools/architecture.json` must stay at `"exceptions": []`.
- If admin UI changes too, audit `filament-events`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Event scheduling, venues, registrations, attendance, governance, search indexing.
- Skip when: Ticket inventory/pricing — see ticketing; seat maps — see seating; shared human identity — see persons.
- Owner/security: event roots use `commerce-support` `HasOwner`; event children inherit the event boundary through `Platform\ScopesByEventOwner` (`event_owner` scope); venue/taxonomy catalogs are intentional global vocabularies.

## Key surfaces
- Features: `Catalog` (events/occurrences/sessions/involvements/series/templates), `Venues`, `Taxonomy`, `Registrations`, `Attendance`, `Commerce` (checkout/orders/ticketing bridge), `Changes`, `Governance` (submissions/moderation/escalations), `Search`.
- Api contracts: `Features\Catalog\Api\CatalogApi`, `Features\Registrations\Api\RegistrationsApi` (+ `RegistrationSummary`, `EventRegistrationParticipantData`, `EventRegistrationOrderItemRef`), `Features\Attendance\Api\AttendanceApi`, `Features\Changes\Api\ChangesApi`, `Features\Governance\Api\GovernanceApi`.
- Contracts: `RegistrationServiceInterface`, `EventLifecycleWorkflow`, `EventCheckInService`, `EventCloneService`, `EventTemplateService`, `EventModerationWorkflow`, `EventSubmissionConverter`, `EventTaxonomyHierarchy`, `EventPassIssuanceService`, plus resolver/driver seams.
- Drivers: `TicketingDriver`, `SeatingDriver`, `OrdersDriver`, `CartCheckoutDriver`, `AuthzDriver`, `EventChangeNoticeNotificationDriver`, `DefaultEventDisplayTimezoneDriver`, `DefaultEventSearchPayloadDriver`, and null-object fallbacks.
- Config `events.php`: `models` (all `null`, per-app overrides via `events.models.*`), `database.tables`, `features` (`owner`, `free_only`, `auto_allocate_seats`, …), `defaults`, `media`, `lifecycle`, `sync`, `attribute_sync`, `search`, `change_notices`, `integrations`, `notifications`.

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-taxonomy-hierarchy.md`, `06-venue-facilities.md`
