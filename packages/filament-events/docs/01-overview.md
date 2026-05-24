---
title: Overview
---

# Filament Events

`aiarmada/filament-events` provides the Filament v5 admin surface for the core [`aiarmada/events`](../../events/docs/01-overview.md) package.

It registers owner-aware resources for:

- event series
- events
- occurrences
- venues
- registrations

## What the plugin adds

- CRUD resources for all event-domain models
- owner-scoped list pages using `OwnerUiScope`
- relation managers for event → occurrences and occurrence → registrations
- registration lifecycle actions like check-in and cancellation
- commerce-aware resource links to products, variants, orders, and customers where the core events package exposes them

## Registered resources

| Resource | Purpose |
| --- | --- |
| `EventSeriesResource` | Manage reusable series/grouping records |
| `EventResource` | Manage the main event definitions |
| `OccurrenceResource` | Manage scheduled runs, venues, and registration windows |
| `VenueResource` | Manage physical venue/contact details |
| `RegistrationResource` | Manage attendees, linked commerce records, and registration lifecycle |

## Navigation

By default the plugin groups its resources under `Events` and orders them like this:

1. Event Series
2. Events
3. Occurrences
4. Venues
5. Registrations

You can customize the group label and sort order through `config/filament-events.php`.

## Requirements

- PHP 8.4+
- Laravel 13+
- Filament 5.6+
- `aiarmada/events`
- `aiarmada/commerce-support`

## Relationship to the core package

`filament-events` does not replace the domain logic in `aiarmada/events`.

Use the core package for:

- event models and enums
- registration services
- order-item fulfillment actions
- owner-scoped domain rules

Use this package for:

- admin CRUD
- owner-safe resource queries
- Filament forms, tables, infolists, and relation managers
