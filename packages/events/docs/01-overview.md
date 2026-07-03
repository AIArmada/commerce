---
title: Events Overview
---

## Introduction

`aiarmada/events` is a reusable event management package for Laravel. It provides the event-domain layer for event definitions, scheduling, venues, speaker management, registration, check-in, attendance tracking, and change management, while integrating ticketing and seating through sibling packages.

## What this package owns

- Event series, event definitions, occurrences, sessions, and venues
- Registration lifecycle (create, approve, cancel, reject, waitlist, check-in)
- Event-scoped registration orchestration for generic ticket types and passes
- Attendance tracking and check-in console
- People roles (speakers, organizers, sponsors, moderators, etc.)
- Venue/location management with geocoding
- Change management with public updates and notifications
- Event submissions and approval workflows
- Series, taxonomy, and classification system
- Seam extensibility via contracts and resolvers

## What this package does not own

- Ticket types, passes, pass transfers, bundle products, or seat layout/allocation primitives; those belong to `aiarmada/ticketing` and `aiarmada/seating`
- Product, variant, pricing, inventory, customer, order, or payment domain logic
- Filament admin surfaces; those belong to `aiarmada/filament-events`
- Application-specific public copy, SEO policy, or editorial workflows

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
| **Pricing Mode** | Defines whether an event is paid, free, or mixed (paid + free ticket types) |
| **Registration Mode** | Defines whether registration is required, optional (no pass issued), or none (open door) |
| **Open Door Mode** | Controls behavior when registration is none: block, walk-in, or headcount |

## Key Features

- Fully polymorphic ownership (any model can own events)
- Event hierarchy with parent/child structure
- Multi-occurrence scheduling with rescheduling, postponement, and delay
- Occurrence-scoped and session-scoped registration and participants
- Venue management with geocoding, map links, and facility tracking
- Family/group registration with per-participant answers
- Seat-map associations and pass-triggered seat allocation through `aiarmada/seating`
- Check-in console with generic pass/QR lookup
- Change management with public updates and notification batches
- Event submissions with approval workflows and reason codes
- Series grouping and taxonomy/classification system
- Optional metadata sync and search document indexing for attributes, audiences, classifications, and time expressions across events, occurrences, and sessions
- **State machines**: Event lifecycle, occurrence, registration, and moderation statuses use `spatie/laravel-model-states` for validated transitions
- Extensibility seams: 15+ contracts for resolvers, workflows, and integrations
- **Free-only mode**: Events can be marked as free with configurable registration behavior (required, optional, or open-door with walk-in/headcount tracking)
- Money values are stored as integer minor units with an explicit currency code.

## Free-Only Event Mode

Events support three **pricing modes** (`PricingMode`):

| Mode | Description |
|---|---|
| `Paid` | Requires ticket types with prices. Registrations go through the commerce checkout. |
| `Free` | No ticket types needed. Registrations use `RegisterForFreeAction`. |
| `Mixed` | Supports both paid ticket types and free registrations. |

And three **registration modes** (`RegistrationMode`):

| Mode | Description |
|---|---|
| `Required` | Registration is mandatory. Passes are issued. |
| `Optional` | Registration is available but not required. Creates `Interested` status registrations without passes; promotable to `Confirmed` later. |
| `None` | Open-door event. Behavior is controlled by `open_door_mode`: block (no registration), walk-in (use `RecordWalkInAction`), or headcount (use `RecordHeadcountLogAction`). |

Each level (Event → Occurrence → Session) can override pricing and registration modes independently, with inheritance to child levels.

## Owner Scoping

Event roots use `commerce-support` owner scoping. Event-bound children inherit the event boundary, series and template children inherit their owning root, and pre-conversion submissions use their target owner. Polymorphic workflow records are resolved only through owner-safe parents. When `events.features.owner.enabled` is `true`, those queries require the current owner or explicit global context.

## Related Packages

- `aiarmada/filament-events` — Filament admin UI for event management
- `aiarmada/commerce-support` — Owner scoping and shared primitives
- `aiarmada/ticketing` — generic ticket types, passes, transfers, and pass issuance
- `aiarmada/seating` — seat maps, holds, and allocations
- `aiarmada/products` — Optional product/variant integration
- `aiarmada/customers` — Optional customer/purchaser integration
- `aiarmada/orders` — Optional order/checkout integration

## Requirements

- PHP 8.4+
- Laravel 11+
- `aiarmada/commerce-support`
