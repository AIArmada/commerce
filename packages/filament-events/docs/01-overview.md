---
title: Overview
---

## Introduction

`aiarmada/filament-events` is the Filament v5 admin adapter for `aiarmada/events`. It provides Filament resources, pages, relation managers, and widgets for managing events, occurrences, sessions, registrations, attendance, venues, taxonomy, and moderation workflows.

Every write goes through the core package's scalar-ID contracts — the adapter never mutates `events` tables directly and never reimplements domain rules.

## What this package owns

- Filament resources for events, occurrences, sessions, venues, registrations, attendance, templates, taxonomy, and change logs
- Custom pages: check-in console, approval queue, and public event preview
- Owner-scoped resource queries using `OwnerUiScope` (event-bound resources only; venue/taxonomy catalogs are global by design)
- Relation managers on the event/occurrence/session detail pages
- Form extension seams (`EventFormExtension`, `EventOccurrenceFormExtension`, `TermFormExtension`) and the default media extension
- Import/export actions for registrations, sessions, venues, events, occurrences, attendances, and registrations

## What this package does not own

- Event-domain persistence or registration business rules; those stay in `aiarmada/events`
- Ticket type or pass CRUD; those stay in `aiarmada/filament-ticketing`
- Product, variant, order, or customer domain logic beyond linked resource access

## Relationship to the core package

Use `aiarmada/events` for:
- Feature `Models`, `Actions`, and `Queries` (Modular Dune layout)
- Scalar-ID contracts (`EventLifecycleWorkflow`, `EventCloneService`, `EventModerationWorkflow`, `EventCheckInService`, `EventTemplateService`, …)
- Immutable DTOs under `AIArmada\Events\Data`
- Config-driven model resolution through `AIArmada\Events\Platform\ModelResolver`
- Owner-scoped domain logic and write guards

Use `aiarmada/filament-ticketing` for:
- Ticket type CRUD
- Pass, holder, and transfer administration
- Generic ticketing resources across event and non-event ticketables

Use this package for:
- Admin CRUD and read-only views
- Owner-safe resource queries
- Filament forms, tables, infolists, and relation managers
- Check-in console, approval queue, and public preview

## Registered Resources

| Resource | Model (default) | Purpose |
|---|---|---|
| `EventResource` | `AIArmada\Events\Features\Catalog\Models\Event` | Manage event definitions, lifecycle, and related records |
| `EventOccurrenceResource` | `AIArmada\Events\Features\Catalog\Models\EventOccurrence` | Manage scheduled runs, capacity, and windows |
| `EventSessionResource` | `AIArmada\Events\Features\Catalog\Models\EventSession` | Manage agenda items within occurrences |
| `EventRegistrationResource` | `AIArmada\Events\Features\Registrations\Models\EventRegistration` | View registrations and participant data |
| `EventRegistrationParticipantResource` | `AIArmada\Events\Features\Registrations\Models\EventRegistrationParticipant` | Manage registration participants |
| `EventAttendanceResource` | `AIArmada\Events\Features\Attendance\Models\EventAttendance` | View check-in and attendance records |
| `VenueResource` | `AIArmada\Events\Features\Venues\Models\Venue` | Manage physical venue/contact details |
| `VenueSpaceResource` | `AIArmada\Events\Features\Venues\Models\VenueSpace` | Manage bookable spaces within a venue |
| `EventChangeLogResource` | `AIArmada\Events\Features\Changes\Models\EventChangeLog` | Audit log of event changes |
| `EventTemplateResource` | `AIArmada\Events\Features\Catalog\Models\EventTemplate` | Reusable event templates |
| `EventTaxonomyResource` | `AIArmada\Events\Features\Taxonomy\Models\EventTaxonomy` | Classification taxonomies |
| `EventTermResource` | `AIArmada\Events\Features\Taxonomy\Models\EventTerm` | Taxonomy terms |

Venue, venue space, taxonomy, and term resources resolve their model through `AIArmada\Events\Platform\ModelResolver`, so host overrides of `events.models.*` are respected.

Bulk import/export is supported via `Actions\Importer\*` (registrations, sessions, venues) and `Actions\Exporter\*` (events, occurrences, sessions, registrations, attendances, venues).

## Relation managers

| Resource | Relation managers |
|---|---|
| Event | Occurrences, Sessions, Locations, Involvements, Registrations, Attendances, Classifications |
| Occurrence | Sessions, Locations, Involvements, Registrations, Attendances |
| Session | Involvements, Locations, Registrations, Attendances, Materials |
| Taxonomy | Terms |

Applications can append their own relation managers through the `*_relation_managers` config keys (see [Configuration](03-configuration.md)).

## Custom Pages

| Page | Purpose |
|---|---|
| **Check-In Console** | Search passes, check in attendees via `EventCheckInService::checkInWithResult()`, record walk-ins |
| **Approval Queue** | Review submissions and drive `EventModerationWorkflow` (approve/reject/assign) |
| **Event Public Preview** | View an event as the public would see it |

## Widgets

| Widget | Purpose |
|---|---|
| **Event Stats** | Dashboard cards for total events, published, upcoming occurrences, registrations, attendances |

## Related Packages

- `aiarmada/events` — core event-domain package
- `aiarmada/filament-ticketing` — ticket type and pass administration
- `aiarmada/commerce-support` — owner scoping support

## Requirements

- PHP 8.5+
- Laravel 13+
- Filament 5.8+
- `aiarmada/events`
- `aiarmada/commerce-support`
