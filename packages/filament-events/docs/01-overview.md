---
title: Overview
---

## Introduction

`aiarmada/filament-events` is the Filament admin adapter for `aiarmada/events`. It provides Filament v5 resources, pages, and widgets for managing events, occurrences, sessions, registrations, ticket types, attendance, and venues.

## What this package owns

- Filament resources for events, occurrences, sessions, venues, registrations, ticket types, and attendance
- Custom pages: check-in console, notification center, approval queue, and public event preview
- Owner-scoped resource queries using `OwnerUiScope`
- Lifecycle workflow actions (publish, cancel, postpone, delay, archive)
- Relation managers on the event detail page (occurrences, sessions, locations, involvements, registrations, ticket types, attendances)

## What this package does not own

- Event-domain persistence or registration business rules; those stay in `aiarmada/events`
- Product, variant, order, or customer domain logic beyond linked resource access

## Relationship to the core package

Use `aiarmada/events` for:
- Event models and enums
- Registration services and business rules
- Order-item fulfillment actions
- Owner-scoped domain logic

Use this package for:
- Admin CRUD and read-only views
- Owner-safe resource queries
- Filament forms, tables, infolists, and relation managers
- Check-in console and notification management

## Registered Resources

| Resource | Model | Purpose |
|---|---|---|
| `EventResource` | `Event` | Manage event definitions, lifecycle, and related records |
| `EventOccurrenceResource` | `EventOccurrence` | Manage scheduled runs, capacity, and windows |
| `EventSessionResource` | `EventSession` | Manage agenda items within occurrences |
| `VenueResource` | `Venue` | Manage physical venue/contact details |
| `EventRegistrationResource` | `EventRegistration` | View registrations and participant data |
| `EventTicketTypeResource` | `EventTicketType` | Manage admission definitions and pricing |
| `EventAttendanceResource` | `EventAttendance` | View check-in and attendance records |

## Custom Pages

| Page | Purpose |
|---|---|
| **Check-In Console** | Search passes, check-in attendees, record walk-ins |
| **Notification Center** | Create and send notification batches, view deliveries |
| **Approval Queue** | Review and process event submission approvals |
| **Event Public Preview** | View an event as the public would see it |

## Widgets

| Widget | Purpose |
|---|---|
| **Event Stats** | Dashboard cards for total events, published, upcoming occurrences, registrations, attendances |

## Related Packages

- `aiarmada/events` — core event-domain package
- `aiarmada/commerce-support` — owner scoping support

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament 5+
- `aiarmada/events`
- `aiarmada/commerce-support`
