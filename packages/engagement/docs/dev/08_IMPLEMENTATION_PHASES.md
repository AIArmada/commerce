# 08 — Implementation Phases

## Phase 1 — Package foundation

Goal: create package skeleton and configuration.

Tasks:

- [x] Create Laravel package structure.
- [x] Create `EngagementServiceProvider`.
- [x] Publish config file `interactions.php`.
- [x] Add model namespace configuration.
- [x] Add migration publishing/loading strategy.
- [x] Add no-FK/no-soft-delete migration conventions.
- [x] Bind service contracts to concrete implementations.
- [x] Add Null-safe integration hooks.

## Phase 2 — Database migrations

Goal: create interaction tables.

Tasks:

- [x] Create `follows` migration.
- [x] Create `bookmarks` migration.
- [x] Create `bookmark_collections` migration.
- [x] Create `bookmark_collection_items` migration.
- [x] Create `responses` migration.
- [x] Create `reactions` migration.
- [x] Create `subscriptions` migration.
- [x] Create `reminders` migration.
- [x] Optionally create `interaction_counters` migration.
- [x] Add indexes but no foreign keys.
- [x] Use UUID primary ids.
- [x] Use timestampTz/timestampsTz correctly.

## Phase 3 — Models and state transitions

Goal: implement Eloquent models and safe lifecycle methods.

Tasks:

- [x] Create models.
- [x] Add casts for JSON and timestamps.
- [x] Add scopes.
- [x] Add lifecycle methods. (constants for status codes)
- [x] Add model factories. (10 factories for all Engagement models)
- [x] Add constants/classes for status codes.
- [x] Ensure no soft delete trait is used.

## Phase 4 — Contracts and traits

Goal: let external packages plug in.

Tasks:

- [x] Create `Followable`, `Bookmarkable`, `Respondable`, `Reactable`, `Subscribable`, `Remindable` contracts.
- [x] Create actor contracts/traits.
- [x] Create subject traits.
- [x] Ensure traits only define relationships and convenience wrappers.
- [x] Ensure real logic goes through services.

## Phase 5 — Services

Goal: implement core interaction behavior.

Tasks:

- [x] Implement `EngagementManager`.
- [x] Implement `SubscriptionManager`.
- [x] Implement `ReminderManager`.
- [x] Implement `EngagementStateResolver`.
- [x] Implement `EngagementCounterService`.
- [x] Implement `EngagementPolicyResolver`.
- [x] Add idempotency guards. (duplicate active-follow/bookmark checks)
- [~] Add application-side uniqueness checks. (partially in services; no test coverage)

## Phase 6 — Laravel events and notification integration

Goal: support extension through events/listeners.

Tasks:

- [x] Dispatch follow/bookmark/response/reaction events.
- [x] Dispatch subscription/reminder events.
- [x] Add reminder due command.
- [x] Add subscription matcher service. (DefaultSubscriptionManager::matchingSubscriptions())
- [x] Add default notification classes or stubs. (InteractionReminderNotification)
- [ ] Ensure host app can override notification behavior. (channels configurable; notification class override not wired)

## Phase 7 — Events package integration

Goal: move event-specific interaction tables out of Events and integrate cleanly.

Tasks:

- [x] Add `EventEngagementManager` implementation in Interactions package. (EngagementEventEngagementManager)
- [x] Update Events package to bind Null adapter by default. (EventsServiceProvider)
- [x] Update Events package to bind real adapter when Interactions exists. (EventsServiceProvider class_exists check)
- [x] Remove event-specific interaction tables from Events migrations/docs. (done)
- [ ] Update Events Filament resources to use contracts. (Filament not built)
- [ ] Add tests where Events works with and without Interactions package. (not done)

## Phase 8 — Filament admin

Goal: build full admin counterpart.

Tasks:

- [x] Create Filament resources. (7 resources: Follow, Bookmark, BookmarkCollection, Response, Reaction, Subscription, Reminder)
- [x] Create relation managers. (6 reusable relation managers)
- [x] Create dashboard widgets. (EngagementOverviewWidget)
- [x] Add actions. (8 reusable Filament actions)
- [x] Add filters. (select filters on all resources)
- [x] Add package config to enable/disable resources. (config exists)

## Phase 9 — Testing and acceptance

Goal: verify behavior and package boundaries.

Tasks:

- [ ] Unit tests for services. (not started)
- [ ] Feature tests for each interaction type. (not started)
- [ ] Integration tests with Events package. (not started)
- [ ] Integration tests without Events package. (not started)
- [ ] Filament resource smoke tests. (not started)
- [ ] Notification/reminder command tests. (not started)
- [ ] Counter reconciliation tests. (not started)
- [ ] Migration tests for no FK/no soft deletes. (not started)
