---
title: Events Overview
---

## Introduction

`aiarmada/events` is a reusable event management package for Laravel built on the Modular Dune architecture. It provides the event-domain layer for event definitions, scheduling, venues, taxonomy, registration, check-in, attendance tracking, governance, search indexing, and change management, while integrating ticketing and seating through sibling packages.

## Package layout

```
packages/events/
├── src/
│   ├── Contracts/       # 40+ pure scalar-ID interfaces (no Eloquent, no Illuminate runtime types)
│   ├── Data/            # final readonly DTOs + backed enums, zero Spatie LaravelData
│   ├── Drivers/         # external adapters (ticketing, seating, orders, cart, authz, notifications)
│   ├── Events/          # dispatched domain events
│   ├── Exceptions/
│   ├── Features/        # 9 owned capabilities (see below)
│   ├── Notifications/
│   ├── Platform/        # ModelResolver, ExtensionRegistry, FeatureCatalog, EventWriteGuard, scoping traits
│   ├── Traits/          # OwnsEvents, CanOrganizeEvents
│   └── EventsServiceProvider.php
├── config/events.php
├── database/
├── docs/
├── resources/boost/     # downstream Laravel Boost guidelines + skill
└── tools/               # architecture.json + package-check.php + PHPStan OwnedWriteRule
```

Each feature folder contains `feature.json` (manifest), `FeatureModule` (registration/boot hooks), and any of `Actions/`, `Api/`, `Queries/`, `Models/`, `Policies/`, `States/`, `Extensions/`, `EntryPoints/`.

## The 9 features

| Feature | Owns | Summary |
|---|---|---|
| **Catalog** | `events`, `event_occurrences`, `event_sessions`, involvements, series, templates, media/references/links/languages/audiences/eligibility/itineraries/attributes/availability/revisions/recurrence/organizers | Event graph, lifecycle workflow, cloning, template service, reference/translation resolvers |
| **Venues** | `event_venues`, `event_venue_spaces`, `event_venue_space_types`, `event_locations`, `event_facility_types`, `event_venue_facilities`, `event_facilities` | Venues, spaces, place-facility catalog, venue facility sync/seed |
| **Taxonomy** | `event_taxonomies`, `event_terms`, `event_classifications`, `event_term_policies` | Hierarchical vocabularies, classification sync, primary-classification invariant |
| **Registrations** | `event_registrations`, `event_registration_participants`, `event_registration_answers`, `event_registration_items`, `event_registration_questions` | Registration service, free RSVP, waitlist promotion, capacity queries, bundle children |
| **Attendance** | `event_attendances`, `event_attendance_logs`, `event_walk_ins`, `event_headcount_logs` | Check-in/check-out service, walk-ins, headcount logs |
| **Commerce** | *nothing* (bridge only) | Checkout step contributor, pass issuance/revocation listeners, order fulfillment sync, refunds, seat allocation |
| **Changes** | `event_change_logs`, `event_updates`, `event_update_items` | Change chain dispatch/revert, public notices, notification delivery |
| **Governance** | `event_submissions`, submission logs/attachments, approval requests, management assignments (+requests), verifications, reports, moderation actions, escalations | Submissions, moderation workflow, assignment requests, escalation resolution |
| **Search** | `event_search_documents` | Search document builder/engine, observers, queueable indexing job |

Cross-feature calls go through feature `Api` contracts only; sibling features cannot import another feature's internal `Models`, `Actions`, or `Queries`.

## Core Concepts

| Concept | Description |
|---|---|
| **Event** | Top-level entity representing a program, conference, workshop, or gathering |
| **Occurrence** | Individual scheduled instance of an event (a specific date/time run) |
| **Session** | Agenda item or program segment within an occurrence (keynote, panel, workshop) |
| **Venue** | Physical location where events take place |
| **Registration** | Formal signup for an event, supporting individual, family, and group |
| **Participant** | Person included in a registration; can be scoped directly to an occurrence or session |
| **Ticket Type** | Admission/access definition from `aiarmada/ticketing`, scoped to an event, occurrence, or session |
| **Pass** | Issued credential from `aiarmada/ticketing`, optionally linked to seat allocations from `aiarmada/seating` |
| **Attendance** | Check-in record tracking who actually attended |
| **Involvement** | People linked to event/occurrence/session with a role (speaker, organizer, sponsor). Organizers are involvements with `role_code = 'organizer'`. |
| **Pricing Mode** | Defines whether an event is paid, free, or mixed (paid + free ticket types); `aiarmada/ticketing` `PricingMode` enum |
| **Registration Mode** | `AIArmada\Events\Data\RegistrationMode`: required, optional (no pass issued), or none (open door) |
| **Open Door Mode** | `AIArmada\Events\Data\OpenDoorMode`: block, walk-in, or headcount |

## Scalar-ID public surface

The package no longer exposes Eloquent models or Spatie Data DTOs across its boundaries. Application code calls:

- **Contracts** (`AIArmada\Events\Contracts`) — `RegistrationServiceInterface`, `EventLifecycleWorkflow`, `EventCheckInService`, `EventCloneService`, `EventTemplateService`, `EventModerationWorkflow`, `EventSubmissionConverter`, `EventTaxonomyHierarchy`, `EventPassIssuanceService`, `EventRefundEligibility`, and resolver seams. All methods take and return IDs, arrays, and immutable DTOs.
- **Feature APIs** — `Features\Catalog\Api\CatalogApi`, `Features\Registrations\Api\RegistrationsApi`, `Features\Attendance\Api\AttendanceApi`, `Features\Changes\Api\ChangesApi`, `Features\Governance\Api\GovernanceApi`.
- **Data DTOs** (`AIArmada\Events\Data`) — `AttendanceRecord`, `EventCheckInResult`, `EventRegistrationScopeData`, `EventCartLineData`, `EventOrderData`, `EventRefundStateData`, enums such as `RegistrationMode`, `OpenDoorMode`, `ScheduleKind`, `EventVisibility`, `FacilityAvailability`. All are `final readonly` PHP classes or native enums — no `spatie/laravel-data`.
- **Model resolution** — `Platform\ModelResolver` maps 23 config keys (`events.models.*`) to configured Eloquent classes; features register defaults at boot when a key is `null`.

Eloquent models remain available for hosts inside their own feature boundaries (Filament resources, app code that opts in), but package internals never trust caller-supplied models for cross-feature work.

## Extensibility

- **Contracts + Drivers** — bind a custom implementation for any contract in a service provider. External integrations resolve through `Drivers/` classes behind contracts (never imported directly by `Platform` or `Features`).
- **ExtensionRegistry slots** — features publish pliable units through `feature.json` `extensions`:
  - `events.cascade-delete` — `EventScopeCascadeDeleter` implementations invoked when an event/occurrence/session subtree is deleted.
  - `events.clone-scope` — `EventScopeCloner` implementations invoked by the clone service.
  - Resolve slots with `app(ExtensionRegistry::class)->resolve('events.cascade-delete', EventScopeCascadeDeleter::class)`.
- **Config seams** — `events.classifications.resolver`, `events.references.resolver`, `events.translations.provider`, `events.time_expressions.resolver`, `events.schedule.resolver`, `events.timezone.display_timezone_resolver`, `events.search.*`, `events.change_notices.audience_resolver`, `events.integrations.*`.

## Key Features

- Fully polymorphic ownership (any owner model can own events)
- Event hierarchy with parent/child structure
- Multi-occurrence scheduling with rescheduling, postponement, and delay; `rescheduled` is an explicit state
- Occurrence-scoped and session-scoped registration and participants
- Venue management with addressing, space catalog, and place facilities
- Family/group registration with per-participant answers
- Seat-map associations and pass-triggered seat allocation through `aiarmada/seating`
- Check-in console with generic pass/QR lookup
- Change management with public updates and a change-notice notification seam
- Event submissions with approval workflows and reason codes
- Series grouping and taxonomy/classification system
- Relation-backed search document indexing for attributes, audiences, classifications, and time expressions across events, occurrences, and sessions
- **State machines**: event, occurrence, registration, and moderation statuses use `spatie/laravel-model-states` for validated transitions
- **Free-only mode**: events can be free with configurable registration behavior (required, optional, or open-door with walk-in/headcount tracking)
- Money values are stored as integer minor units with an explicit currency code.

## Free-Only Event Mode

Events support three **pricing modes** (`AIArmada\Ticketing\Enums\PricingMode`):

| Mode | Description |
|---|---|
| `Paid` | Requires ticket types with prices. Registrations go through the commerce checkout. |
| `Free` | No ticket types needed. Registrations use `RegisterForFreeAction`. |
| `Mixed` | Supports both paid ticket types and free registrations. |

And three **registration modes** (`AIArmada\Events\Data\RegistrationMode`):

| Mode | Description |
|---|---|
| `Required` | Registration is mandatory. Passes are issued. |
| `Optional` | Registration is available but not required. Creates `Interested` status registrations without passes; promotable to `Confirmed` later. |
| `None` | Open-door event. Behavior is controlled by `open_door_mode`: block (no registration), walk-in (use `RecordWalkInAction`), or headcount (use `RecordHeadcountLogAction`). |

Each level (Event → Occurrence → Session) can override pricing and registration modes independently, with inheritance to child levels.

## Owner Scoping

Event roots use `commerce-support` owner scoping (`HasOwner` on `Event`, `EventSeries`, `EventTemplate`, `EventOrganizer`, `EventItinerary`, `EventHeadcountLog`, `EventWalkIn`). Event-bound children inherit the event boundary through `AIArmada\Events\Platform\ScopesByEventOwner` (`event_owner` global scope) and pre-conversion submissions use their target owner through `EventSubmissionOwnerScope`. Writes resolve the owning event first through `Platform\EventWriteGuard::findOrFail()`. When `events.features.owner.enabled` is `true`, those queries require the current owner or explicit global context.

## Related Packages

- `aiarmada/filament-events` — Filament admin UI for event management
- `aiarmada/commerce-support` — Owner scoping and shared primitives
- `aiarmada/ticketing` — generic ticket types, passes, transfers, and pass issuance
- `aiarmada/seating` — seat maps, holds, and allocations
- `aiarmada/addressing` — canonical address attachments for venues and locations
- `aiarmada/contacting` — contact methods and social profiles
- `aiarmada/products` — Optional product/variant integration
- `aiarmada/customers` — Optional customer/purchaser integration
- `aiarmada/orders` — Optional order/checkout integration

## Requirements

- PHP 8.5+
- Laravel 13+
- `aiarmada/commerce-support`

> **warning**
>
> This release is a breaking Modular Dune reorganization. Legacy namespaces directly under `AIArmada\Events` (the old `Models`, `Enums`, `States`, `Actions`, `Services`, `Support`, `Resolvers`, `Listeners`, `Observers`, `Policies`, `Jobs`, `Steps`, `Checkout`, `Integrations`, and `Facades` directories), the `Events` facade, and the Spatie LaravelData DTOs are removed. Classes now live under `Features`, `Contracts`, `Data`, `Drivers`, and `Platform`; scalar IDs replace model parameters on every public contract. See [Troubleshooting](99-troubleshooting.md) for a migration table.
