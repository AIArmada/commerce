---
title: Overview
---

# Events Package

## Purpose

`aiarmada/events` owns the event-domain layer for Commerce applications: series, reusable event definitions, venues, scheduled occurrences, and attendee registrations.

## What this package owns

- Event series, event definitions, venues, occurrences, and registrations
- Registration creation, batch fulfillment, check-in, cancellation, and attendee lifecycle rules
- Occurrence capacity plus registration and check-in opening / closing windows
- Event-domain actions such as `EnsureOccurrenceAction` for idempotent series / event / venue / occurrence upserts

## What this package does not own

- Product, variant, pricing, inventory, customer, order, or payment domain logic, even when events link to those records
- Filament admin surfaces; those belong to `aiarmada/filament-events`

## Related packages

- [`aiarmada/filament-events`](../../filament-events/docs/01-overview.md) — Filament resources and registration lifecycle UI
- [`aiarmada/products`](../../products/docs/01-overview.md), [`aiarmada/customers`](../../customers/docs/01-overview.md), and [`aiarmada/orders`](../../orders/docs/01-overview.md) — related commerce records the events package can link to
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner scoping and shared primitives

## Main models services or surfaces

- **Models** — `EventSeries`, `Event`, `Venue`, `Occurrence`, `Registration`
- **Enums** — `EventStatus`, `OccurrenceStatus`, `RegistrationStatus`
- **Actions** — `EnsureOccurrenceAction` plus order-fulfillment helpers for creating registrations from commerce orders
- **Services** — `RegistrationService` for single create, batch create, check-in, and cancellation

## Owner scoping and security notes

- Event models are owner-aware and follow the `commerce-support` owner-boundary rules
- Public registration mutations derive owner context from the occurrence or registration they act on
- Persisted global occurrences and registrations still require explicit global context before mutation
- Capacity and availability checks live in the service layer, not only in UI forms

## Highlights

- Owner-aware event models powered by `commerce-support`
- Event series and reusable event topics
- Venue and scheduled occurrence modeling
- Capacity-aware occurrences with registration and check-in windows
- Registration service that enforces sold-out, availability, and lifecycle rules
- Idempotent upsert flow for syncing series, events, venues, and occurrences
- Native integration points for products, variants, orders, and customers

## Core models

| Model | Responsibility |
| --- | --- |
| `EventSeries` | Groups related topics under one program or brand |
| `Event` | Reusable event definition |
| `Venue` | Physical location details |
| `Occurrence` | A scheduled run of an event with capacity and registration / check-in windows |
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

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Troubleshooting](99-troubleshooting.md)
- [Filament Events overview](../../filament-events/docs/01-overview.md)
