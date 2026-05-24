---
title: Overview
---

# Events Package

## Purpose

The `aiarmada/events` package owns the event-domain layer for Commerce applications: series, events, venues, occurrences, attendee registrations, and registration lifecycle operations.

## What this package owns

- Event series, event definitions, venues, occurrences, and registrations
- Registration creation, check-in, cancellation, and attendee lifecycle rules
- Event-specific status enums and event-domain relationships

## What this package does not own

- Product, variant, pricing, inventory, customer, order, or payment domain logic, even when events reference those records
- Filament admin surfaces; those belong to `aiarmada/filament-events`

## Related packages

- [`aiarmada/filament-events`](../../filament-events/docs/01-overview.md) — Filament resources and registration lifecycle UI
- [`aiarmada/products`](../../products/docs/01-overview.md), [`aiarmada/customers`](../../customers/docs/01-overview.md), and [`aiarmada/orders`](../../orders/docs/01-overview.md) — related commerce records the events package can link to
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner scoping and shared primitives

## Main models services or surfaces

- **Models** — `EventSeries`, `Event`, `Venue`, `Occurrence`, `Registration`
- **Enums** — event, occurrence, and registration status enums
- **Domain surface** — registration service, check-in lifecycle, and commerce integration hooks

## Owner scoping and security notes

- Event models are owner-aware and should follow the `commerce-support` owner-boundary rules
- Registration and occurrence mutations should resolve their target records in the current owner scope before writes occur

`aiarmada/events` provides the event-domain layer for Commerce applications: series, events, venues, occurrences, and attendee registrations.

## Highlights

- Owner-aware event models powered by `commerce-support`
- Event series and reusable event topics
- Venue and scheduled occurrence modeling
- Registration records separated from generic customers
- Registration service for attendee creation, check-in, and cancellation
- Native integration points for products, variants, orders, and customers

## Core models

| Model | Responsibility |
| --- | --- |
| `EventSeries` | Groups related topics under one program or brand |
| `Event` | Reusable event definition |
| `Venue` | Physical location details |
| `Occurrence` | A scheduled run of an event |
| `Registration` | One attendee entitlement for one occurrence |

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

## Boundary

The events package owns:

- occurrences
- venues
- attendee registrations
- check-in lifecycle

The commerce packages continue to own:

- products
- variants
- pricing
- inventory
- customers
- orders
- payments

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Troubleshooting](99-troubleshooting.md)
- [Filament Events overview](../../filament-events/docs/01-overview.md)
