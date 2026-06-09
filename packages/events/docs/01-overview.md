---
title: Overview
---

# Events Package

## Purpose

`aiarmada/events` owns the reusable event-domain layer for AIArmada applications: public event definitions, series, organizers, people roles, venues / locations, scheduled occurrences, participation modes, attendee registrations, and optional commerce fulfillment.

## What this package owns

- Event series, event definitions, organizer links, people-role links, venues / locations, occurrences, and registrations
- Public event moderation, visibility, publication-window, media-reference, taxonomy, search-payload, and display-timezone seams
- Registration creation, walk-in attendance, batch fulfillment, check-in, cancellation, and attendee lifecycle rules
- Occurrence capacity plus registration and check-in opening / closing windows
- Event-domain actions such as `EnsureOccurrenceAction` for idempotent series / event / venue / occurrence upserts
- Adapter seams for host event models, host venue models, organizer / people identities, attendee identity, lifecycle status rules, search indexing, display timezone, media, taxonomy, and order-item fulfillment

## What this package does not own

- Product, variant, pricing, inventory, customer, order, or payment domain logic, even when events link to those records
- Filament admin surfaces; those belong to `aiarmada/filament-events`
- Application-specific public copy, SEO policy, submission workflow, editorial workflow, recurring schedule generation, or app-specific event semantics

Applications can either use `AIArmada\Events\Models\Event` as their base event model or keep a richer host model as canonical and configure occurrences to point at it through `events.models.event`.

## Related packages

- [`aiarmada/filament-events`](../../filament-events/docs/01-overview.md) — Filament resources and registration lifecycle UI
- [`aiarmada/products`](../../products/docs/01-overview.md), [`aiarmada/customers`](../../customers/docs/01-overview.md), and [`aiarmada/orders`](../../orders/docs/01-overview.md) — related commerce records the events package can link to
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner scoping and shared primitives

## Main models services or surfaces

- **Models** — `EventSeries`, `Event`, `EventPerson`, `Venue`, `Occurrence`, `Registration`
- **Enums** — `EventStatus`, `EventModerationStatus`, `EventVisibility`, `OccurrenceStatus`, `RegistrationStatus`
- **Actions** — `EnsureOccurrenceAction` plus order-fulfillment helpers for creating registrations from commerce orders
- **Services** — `RegistrationService` (behind `RegistrationServiceInterface`) for single create, batch create, check-in, and cancellation

## Owner scoping and security notes

- Event models are owner-aware and follow the `commerce-support` owner-boundary rules
- Public registration mutations derive owner context from the occurrence or registration they act on
- Persisted global occurrences and registrations still require explicit global context before mutation
- Capacity and availability checks live in the service layer, not only in UI forms

## Highlights

- Owner-aware event models powered by `commerce-support`
- Event series and reusable event topics
- Venue and scheduled occurrence modeling
- Organizer and people links without requiring a specific CRM or member model
- Event moderation, visibility, publication windows, media references, taxonomy payloads, and generic search payloads
- Capacity-aware occurrences with registration and check-in windows
- Participation modes for no registration, registration-required, walk-in-only, and hybrid events
- Registration service that enforces sold-out, availability, and lifecycle rules
- Idempotent upsert flow for syncing series, events, venues, and occurrences
- Collision-resistant default tables such as `events` and `event_occurrences`
- Generic attendee morph support for non-customer attendee identities
- Display timezone resolver for app/viewer-specific presentation behavior
- Config-backed lifecycle policy rules for capacity, check-in, and terminal statuses
- Optional first-party integration points for products, variants, orders, and customers

## Optional commerce integrations

`aiarmada/events` can run as a core event lifecycle package with only `aiarmada/commerce-support` installed.

When `aiarmada/products`, `aiarmada/customers`, and `aiarmada/orders` are installed in the same application, the package automatically enables the first-party commerce features:

- product and variant relationships on events and occurrences
- customer-backed purchaser and participant relationships on registrations
- metadata-driven checkout and order-item fulfillment for paid occurrences
- optional override resolvers for checkout intent and order-item fulfillment
- ended-event order finalization command and check-in completion listener

If those packages are not installed, the core event, occurrence, venue, registration, capacity, and check-in lifecycle still works. Commerce-specific relationship methods throw a clear integration error when called without their matching package.

When the order and checkout packages are installed, the package can resolve paid-occurrence checkout intents and fulfill matching order items from event checkout metadata. Applications can still override those defaults with their own resolvers.

## Core models

| Model | Responsibility |
| --- | --- |
| `EventSeries` | Groups related topics under one program or brand |
| `Event` | Reusable public event definition with organizer, moderation, visibility, media, taxonomy, and search seams |
| `EventPerson` | Ordered people-role links for display-only names or app-owned person models |
| `Venue` | Physical, online, or hybrid location details |
| `Occurrence` | A scheduled run of an event with capacity and registration / check-in windows |
| `Registration` | One attendee entitlement for one occurrence |

## Public event layer

The package `Event` model is intended to be a reusable base for serious event applications. It includes:

- `organizer_type` / `organizer_id` morphs for institutions, organizations, creators, or other host-owned organizer records
- ordered `EventPerson` links that can point to host person models or store display-only person names
- `moderation_status` for pending / approved / rejected review flows
- `visibility` for public, unlisted, and private records
- `published_at`, `public_starts_at`, and `public_ends_at` publication windows
- `media_references` and `taxonomy` JSON payloads for package-neutral references and adapter-backed enrichments
- `toSearchableArray()` delegated through `EventSearchPayloadResolver`
- `displayTimezone()` delegated through `EventDisplayTimezoneResolver`

Application-specific submission workflows can sit on top of these primitives without becoming part of the package's core event model.

## Participation modes

Occurrences default to `registration_required`, which preserves the package's original behavior.

| Mode | Value | Registrations | Walk-ins |
| --- | --- | --- | --- |
| No attendance tracking | `none` | No | No |
| Registration required | `registration_required` | Yes | No |
| Walk-in only | `walk_in_only` | No | Yes |
| Hybrid | `hybrid` | Yes | Yes |

Hybrid mode is for events where attendees can pre-register but walk-ins can also be recorded at the door. Both registrations and walk-ins share occurrence capacity.

## Core enums

### Event status

| Case | Value |
| --- | --- |
| `Draft` | `draft` |
| `Active` | `active` |
| `Archived` | `archived` |

### Occurrence status

| Case | Value |
| --- | --- |
| `Draft` | `draft` |
| `Scheduled` | `scheduled` |
| `Live` | `live` |
| `Completed` | `completed` |
| `Cancelled` | `cancelled` |

### Registration status

| Case | Value |
| --- | --- |
| `Pending` | `pending` |
| `Confirmed` | `confirmed` |
| `CheckedIn` | `checked_in` |
| `Cancelled` | `cancelled` |
| `Refunded` | `refunded` |
| `NoShow` | `no_show` |
| `Waitlisted` | `waitlisted` |

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Domain invariants](05-invariants.md)
- [Troubleshooting](99-troubleshooting.md)
- [Filament Events overview](../../filament-events/docs/01-overview.md)
