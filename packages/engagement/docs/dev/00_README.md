# Generic Engagement Package — Developer Handoff Pack

This handoff pack is the source-of-truth specification for building a reusable Laravel Interactions package.

The package owns **persistent user intent and preference** toward any model. It must be generic enough to support Events, ilmu360, speakers, masjids, books, articles, products, topics, courses, organizations, and future application domains.

It must not become an Analytics package, Notifications package, Commerce package, Authorization package, or Events package.

## Package boundary

```text
Interactions package
= intentional user state
= follow, save/bookmark, collect, respond, react, subscribe, reminder preference

Analytics package
= passive tracking and metrics
= viewed, clicked, opened, searched, impressions, funnels, conversions

Events package
= event truth and operations
= event, occurrence, session, registrations, passes, attendance, location, updates

Laravel Notifications
= actual notification delivery
= mail, database notifications, broadcast, WhatsApp/SMS integrations through app channels

Commerce / Checkout / Orders / Payments
= paid access, invoices, orders, payments, refunds
```

## Files in this pack

1. `01_ARCHITECTURE_PRINCIPLES.md` — package purpose, boundaries, naming, generic vs domain-specific split.
2. `02_DATABASE_SCHEMA.md` — full table-by-table structure with UUID primary keys, timestampTz/timestampsTz, no soft deletes, no database foreign keys, and no cascades.
3. `03_MODELS_RELATIONSHIPS.md` — models, relationships, scopes, and package usage examples.
4. `04_CONTRACTS_TRAITS_SERVICES.md` — PHP interfaces, traits, service contracts, adapters, null implementations, and extension points.
5. `05_EVENTS_PACKAGE_MIGRATION_AND_INTEGRATION.md` — explicit instruction to move tables/concepts out of Events and into Engagement, plus integration hooks.
6. `06_LARAVEL_NOTIFICATIONS_INTEGRATION.md` — how Engagement uses Laravel Notifications without owning a Notifications package.
7. `07_FILAMENT_ADMIN_SPEC.md` — Filament resources, pages, relation managers, dashboards, widgets, actions, and global search.
8. `08_IMPLEMENTATION_PHASES.md` — phased implementation plan.
9. `09_PARALLEL_AGENT_CHECKLISTS.md` — parallel workstreams so multiple agents can build without colliding.
10. `10_TESTING_ACCEPTANCE.md` — acceptance criteria, tests, quality gates, and expected behaviors.

## Non-negotiable technical rules

- Use UUID primary keys for every package table.
- Use `timestampTz()` for single timestamp columns.
- Use `timestampsTz()` for `created_at` and `updated_at` where the record is mutable.
- Do not use soft deletes. No `deleted_at` columns.
- Do not use database foreign key constraints.
- Do not use cascading deletes.
- Enforce integrity through application-side services, validators, policies, state transitions, and cleanup jobs.
- Use polymorphic references with `{subject}_type` and `{subject}_id` where the package must support any external model.
- Use status and lifecycle timestamps instead of booleans where a state transition is being represented.
- Use booleans only for durable properties such as `is_default`, `is_primary`, `is_featured`, `is_system`, `is_public_counter_visible`.
- Use string codes for stable classification values: `status`, `visibility`, `response_type`, `reaction_type`, `subscription_type`, `notification_level`, `reminder_type`.
- Use JSONB for flexible options and metadata, but do not hide first-class searchable concepts in metadata.

## Core mental model

```text
follows
= entity-based interest: follow this event, speaker, masjid, topic, series, organization

bookmarks
= save this item for later

bookmark_collections
= organize saved items into user-owned lists

responses
= explicit intent: interested, going, maybe, not going, attending_online, want_recording

reactions
= lightweight reaction: like, love, useful, amin, support, insightful

subscriptions
= rule/filter-based interest: notify me about live online events, new fiqh talks, recordings by followed speakers

reminders
= user-requested scheduled reminder preference for a subject; actual sending uses Laravel Notifications
```

## Events package integration summary

The Events package must no longer create these tables:

```text
event_responses
event_subscriptions
event_reminders
follows
bookmarks
bookmark_collections
reactions
interaction_events / interaction_logs
```

Instead:

```text
event_responses      -> engagement.responses
event_subscriptions  -> engagement.subscriptions or follows depending on semantics
event_reminders      -> engagement.reminders using Laravel Notifications for delivery
follows/bookmarks/reactions -> engagement package
interaction_events/logs -> analytics package, not engagement or events
```

Events must integrate through contracts, adapters, Laravel events/listeners, and optional service-provider binding. It must not require this package to be installed.

## Instruction to developer AI agents

Do not redesign this package. Implement this specification unless there is a concrete technical blocker. If you must change anything, document:

1. What cannot be implemented as written.
2. Why it is unsafe or impossible.
3. The smallest compatible alternative.
4. Which files/checklists need to be updated.
