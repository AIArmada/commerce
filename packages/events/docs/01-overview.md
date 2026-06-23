---
title: Events Overview
---

## Introduction

`aiarmada/events` is a reusable event management package for Laravel. It provides a complete event-domain layer: event definitions, scheduling, venues, speaker management, registration, ticketing, check-in, attendance tracking, and change management.

## What this package owns

- Event series, event definitions, occurrences, sessions, and venues
- Registration lifecycle (create, approve, cancel, reject, waitlist, check-in)
- Ticket types, passes, and seat management
- Attendance tracking and check-in console
- People roles (speakers, organizers, sponsors, moderators, etc.)
- Venue/location management with geocoding
- Change management with public updates and notifications
- Event submissions and approval workflows
- Series, taxonomy, and classification system
- Seam extensibility via contracts and resolvers

## What this package does not own

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
| **Ticket Type** | Admission/access definition (General, VIP, Early Bird, etc.) |
| **Pass** | Actual issued credential (QR code, barcode) for access |
| **Attendance** | Check-in record tracking who actually attended |
| **Involvement** | People linked to event/occurrence/session with a role (speaker, organizer, sponsor) |
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
- Seat maps with section and reserved seating
- Check-in console with pass/QR lookup
- Change management with public updates and notification batches
- Event submissions with approval workflows and reason codes
- Series grouping and taxonomy/classification system
- **State machines**: Event lifecycle, occurrence, registration, and moderation statuses use `spatie/laravel-model-states` for validated transitions
- Extensibility seams: 15+ contracts for resolvers, workflows, and integrations
- **Free-only mode**: Events can be marked as free with configurable registration behavior (required, optional, or open-door with walk-in/headcount tracking)

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

All event models are owner-aware using `commerce-support` owner scoping. When `events.owner.enabled` is `true`, all queries are automatically scoped to the current owner. Global records are supported for shared events.

## Related Packages

- `aiarmada/filament-events` — Filament admin UI for event management
- `aiarmada/commerce-support` — Owner scoping and shared primitives
- `aiarmada/products` — Optional product/variant integration
- `aiarmada/customers` — Optional customer/purchaser integration
- `aiarmada/orders` — Optional order/checkout integration

## Requirements

- PHP 8.4+
- Laravel 11+
- `aiarmada/commerce-support`
