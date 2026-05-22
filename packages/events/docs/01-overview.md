---
title: Overview
---

# Events Package

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
