# 07 — Implementation Phases (Status)

## Phase 0 — Package foundation
- [x] Package skeleton, service provider, config, namespace, base traits, constants, migration helpers all created.

## Phase 1 — Core schema and models
- [x] `events`, `event_occurrences`, `event_sessions` migrations, models, relationships, factories (no factories yet), lifecycle constants, policies — all done.

## Phase 2 — Locations and facilities
- [x] Venues, spaces, space types, locations, facility types, venue facilities, event facilities — all migrations, models, and contracts done.

## Phase 3 — Roles and involvements
- [x] `event_roles`, `event_involvements` migrations/models, seed defaults, contracts, traits, services — all done.

## Phase 4 — Registration, ticket types, passes
- [x] Access policies, registrations, participants, answers, items, ticket types, components, seating options, passes — migrations/models done. Contracts exist. Services exist (RegistrationService). External order references only.
- [x] Added `event_occurrence_id` / `event_session_id` columns + relationships on `EventRegistrationParticipant` for direct occurrence/session scoping.

## Phase 5 — Seating
- [x] Seat maps, sections, seats, holds, allocations moved to `aiarmada/seating`. Events now links seat maps through polymorphic `SeatMap::seatable`.

## Phase 6 — Attendance and check-in
- [x] Attendances and logs — migrations/models done. EventCheckInService contract needs implementing (see note).

## Phase 7 — Content, audience, discovery, taxonomy, time expressions
- [x] Materials, references, links, media, languages, audiences, profiles, eligibility, taxonomies, terms, classifications, time expressions — all done.

## Phase 8 — Changes, updates, notifications
- [x] Change logs, updates, update items, notification batches, deliveries, subscriptions — ALL tables and models exist.
- [x] Services: EventChangeRecorder, EventChangeImpactClassifier, EventUpdatePublisher, EventNotificationRecipientResolver, EventNotificationDispatcher — contracts and default implementations exist.
- [x] Auto-chain from change_log → event_update → notification_batch. (DispatchEventChangeChainAction implemented and integrated into DefaultEventLifecycleWorkflow)

## Phase 9 — Submissions, approval, management
- [x] Submissions, logs, attachments, approval requests, management assignments — migrations/models done.
- [x] Contracts: AcceptsEventSubmissions, RequiresEventApproval, CanManageEventsFor exist.
- [ ] EventSubmissionConverter contract exists but no default implementation.

## Phase 10 — Series and interactions
- [x] Series, items, rules, itineraries, items — migrations/models done.
- [x] Interaction tables (follows, bookmarks, etc.) moved to Engagement package.

## Phase 11 — Filament admin
- [x] Filament resources moved to packages/filament-events. (7 resources, 4 custom pages, relation managers, lifecycle actions)

## Phase 12 — Hardening and docs
- [x] Model factories. (57 factories for all Events models)
- [x] Tests. (31 passing tests for core workflows)
- [x] User-facing docs. (5 docs: overview, install, config, usage, troubleshooting)
