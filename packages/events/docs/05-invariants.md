---
title: Domain Invariants
---

# Domain Invariants

## Scope

`aiarmada/events` is a reusable event-domain, scheduled occurrence, participation, registration, and ticketing lifecycle package.

It is suitable for public event definitions, organizer and speaker links, venues / locations, occurrence dates, capacity, registration windows, walk-in attendance, check-in, cancellation, and commerce order fulfillment.

It is not a replacement for app-specific editorial policy. Public copy, SEO policy, recommendations, submission workflows, and app-specific publishing rules should stay in the host application.

## Ownership

All package models are owner-aware.

When owner scoping is enabled, writes derive owner context from the target occurrence or registration. Mutating global occurrences and registrations requires explicit global context with `OwnerContext::withOwner(null, ...)`.

The package does not add database-level foreign keys or cascades. Cross-record integrity and cleanup must be enforced in application logic.

## Host Model Adapters

`events.models.event` controls the model returned by `Occurrence::event()` and `EventSeries::events()`.

`events.models.venue` controls the model returned by `Occurrence::venue()`.

Organizer and speaker links are stored as morphs so package-owned and host-owned identity models can coexist.

The package ships its own `Event`, `EventSpeaker`, and `Venue` models as defaults. Host applications with richer event or venue records can configure these keys and create occurrences against the host model IDs.

## Public Event Visibility

Package-owned events are publicly accessible only when all of these are true:

- `status = active`
- `moderation_status = approved`
- `visibility` is `public` or `unlisted`
- `published_at`, `public_starts_at`, and `public_ends_at` allow the current UTC time

Discoverability and search are narrower: only `visibility = public` records should appear in public listings and default search payloads.

`visibility = unlisted` is intended for direct-link access. `visibility = private` is not public.

## Media Taxonomy And Search

The core package stores package-neutral `media_references` and `taxonomy` JSON payloads. It does not force Spatie Media Library, Spatie Tags, Scout, or a specific search engine.

Applications that use those packages should bind adapters around:

- `Event::mediaCollections()`
- `Event::taxonomyTerms()`
- `AIArmada\Events\Contracts\EventSearchPayloadResolver`

## Time

Occurrence input timestamps are interpreted in the occurrence timezone and normalized to UTC by `EnsureOccurrenceAction`.

Service-written lifecycle timestamps such as `checked_in_at` and `cancelled_at` are written in UTC.

The `timezone` column records the intended local timezone for display and schedule interpretation. Consumers should display dates in the viewer or event timezone, not by assuming storage timezone output is user-facing.

Use `Event::displayTimezone()`, `Occurrence::displayTimezone()`, and the `EventDisplayTimezoneResolver` contract when an application needs viewer-specific display behavior.

## Registration Lifecycle

Occurrences declare their package-managed participation behavior through `participation_mode`:

- `none`: no package-managed registration or walk-in attendance
- `registration_required`: pre-registration accepted; walk-ins rejected
- `walk_in_only`: pre-registration rejected; walk-ins accepted
- `hybrid`: pre-registration and walk-ins accepted

Occurrence status eligibility is configured under `events.lifecycle.occurrence`.

Registration status behavior is configured under `events.lifecycle.registration`.

By default:

- `scheduled` and `live` occurrences can accept registrations and check-ins when their time windows allow it.
- `scheduled` and `live` occurrences can accept walk-ins when the participation mode and check-in window allow it.
- only `confirmed` registrations can check in.
- `pending`, `confirmed`, `checked_in`, and `no_show` registrations block capacity.
- `checked_in`, `cancelled`, `refunded`, and `no_show` are terminal statuses for order completion checks.

`waitlisted` registrations do not block capacity by default. The package does not automatically promote waitlisted registrations when capacity opens.

Walk-ins are stored as checked-in registration rows with `attendance_source = walk_in`. Walk-in rows do not require email and emit `WalkInRecorded`, not `RegistrationCheckedIn`, because they are not order-backed registration check-ins.

## Cancellation

Cancellation marks the registration `cancelled`, writes `cancelled_at` in UTC, stores an optional cancellation reason in metadata, and emits `RegistrationCancelled`.

Cancellation does not refund payments, cancel orders, free inventory, or promote waitlists by itself. Those behaviors belong in the host application or commerce package integration.

## Recurring Schedules

The package stores materialized occurrences.

It does not provide an RRULE engine, recurring schedule generator, exception-date planner, or public recurring-series UI. Applications should generate the required occurrence rows and keep recurrence rules in their own domain if needed.

## Fulfillment

Order fulfillment is only registered when the first-party order and customer packages are installed.

Even then, the default `EventOrderItemFulfillmentResolver` is a no-op. Applications must configure `events.integrations.order_item_fulfillment_resolver` to map order items into occurrences and participant payloads.

## Migration Safety

Fresh installs use package-specific tables:

- `event_series`
- `events`
- `event_speakers`
- `event_venues`
- `event_occurrences`
- `event_registrations`

Existing installs that already use older defaults must pin `events.database.tables.*` to their installed table names before running migrations.

Migrations use UUID primary keys and avoid database-level foreign key constraints or cascades. Schema changes should be forward-safe and should not assume package tables own host application records.
