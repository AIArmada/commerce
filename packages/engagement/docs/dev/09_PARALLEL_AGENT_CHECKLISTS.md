# 09 — Parallel Agent Checklists

This file is structured so multiple agents can work in parallel without colliding.

Each agent must own its assigned files and avoid changing other agents' files unless coordination is explicitly required.

## Agent A — Package foundation

### Owns

```text
src/EngagementServiceProvider.php
config/engagement.php
composer.json
README.md
```

### Tasks

- [x] Create package service provider.
- [x] Add config publishing.
- [x] Add migration loading/publishing.
- [x] Bind service contracts.
- [x] Add package feature flags.
- [x] Add optional Filament registration config.
- [x] Add optional Events package adapter detection using `class_exists()`.

### Acceptance

- [x] Package boots without Events package.
- [x] Package boots without Analytics package.
- [x] Package boots without any domain package.
- [x] Config can override model classes. (ModelResolver exists)

---

## Agent B — Database migrations

### Owns

```text
database/migrations/*create_follows_table.php
database/migrations/*create_bookmarks_table.php
database/migrations/*create_bookmark_collections_table.php
database/migrations/*create_bookmark_collection_items_table.php
database/migrations/*create_responses_table.php
database/migrations/*create_reactions_table.php
database/migrations/*create_subscriptions_table.php
database/migrations/*create_reminders_table.php
database/migrations/*create_interaction_counters_table.php optional
```

### Tasks

- [x] Create migrations using UUID primary ids.
- [x] Use `timestampTz` and `timestampsTz`.
- [x] Do not create soft deletes.
- [x] Do not create FK constraints.
- [x] Add useful indexes.
- [x] Add JSONB metadata/preferences/criteria.
- [x] Add comments if migration style supports it. (done)

### Acceptance

- [x] No `foreign()` calls.
- [x] No `constrained()` calls.
- [x] No `cascadeOnDelete()` calls.
- [x] No `softDeletes()` calls.
- [x] Every table has UUID primary id.

---

## Agent C — Models and scopes

### Owns

```text
src/Models/Follow.php
src/Models/Bookmark.php
src/Models/BookmarkCollection.php
src/Models/BookmarkCollectionItem.php
src/Models/Response.php
src/Models/Reaction.php
src/Models/Subscription.php
src/Models/Reminder.php
src/Models/EngagementCounter.php optional
```

### Tasks

- [x] Create models.
- [x] Add casts.
- [x] Add scopes.
- [x] Add relationship methods.
- [x] Add status helper methods. (isActive/isMuted etc added)
- [x] Add factories. (10 factories for all Engagement models)

### Acceptance

- [x] Models do not use SoftDeletes.
- [x] Models use configured table names if package supports prefixing.
- [x] Morph relationships work with any external model.

---

## Agent D — Contracts and traits

### Owns

```text
src/Contracts/*
src/Traits/*
```

### Tasks

- [x] Create Followable/Bookmarkable/Respondable/Reactable/Subscribable/Remindable contracts.
- [x] Create actor traits.
- [x] Create subject traits.
- [x] Keep traits thin.
- [x] Ensure traits call services for mutations.

### Acceptance

- [x] External models can implement contracts without extending package base models.
- [x] Traits are optional.
- [x] No domain-specific class names are referenced.

---

## Agent E — Services

### Owns

```text
src/Services/*
src/Actions/* optional
```

### Tasks

- [x] Implement EngagementManager.
- [x] Implement SubscriptionManager.
- [x] Implement ReminderManager.
- [x] Implement StateResolver.
- [x] Implement CounterService.
- [x] Implement policy resolver.
- [x] Enforce idempotency.
- [x] Dispatch Laravel events.

### Acceptance

- [x] Duplicate active follows are prevented.
- [x] Duplicate active bookmarks are prevented.
- [x] One active response is enforced unless subject allows multiple. (Respondable contract consulted)
- [x] Reactions follow reactable policy. (Reactable contract consulted)
- [ ] Services are transaction-safe where appropriate. (no explicit DB::transaction() wrapping)

---

## Agent F — Events package integration

### Owns

```text
src/Integrations/Events/*
docs/events-integration.md
```

### Tasks

- [x] Create EngagementEventEngagementManager adapter.
- [x] Add optional bridge for Events package.
- [x] Document Events tables to remove. (12_ENGAGEMENT doc has this)
- [ ] Add listener examples for EventOccurrencePublished/EventUpdatePublished. (not created)
- [x] Ensure adapter is not required when Events package is absent.

### Acceptance

- [x] Interactions works without Events.
- [x] Events works without Interactions using Null adapter.
- [x] When both installed, responses/subscriptions/reminders work through Interactions.

---

## Agent G — Laravel notifications / reminders

### Owns

```text
src/Console/SendDueRemindersCommand.php
src/Notifications/* optional
src/Listeners/*Reminder*
```

### Tasks

- [x] Create due reminder command.
- [x] Dispatch ReminderDue.
- [x] Provide default notification stubs if appropriate.
- [ ] Allow host notification override. (channels configurable; notification class not swappable via config)
- [x] Mark sent/failed safely.

### Acceptance

- [x] No notification delivery tables are created.
- [x] Laravel Notifications can be used.
- [ ] Reminder command is idempotent. (not verified; no status check preventing double-send on re-run)

---

## Agent H — Filament admin

### Owns

```text
src/Filament/Resources/*
src/Filament/Widgets/*
src/Filament/Actions/*
src/Filament/RelationManagers/*
```

### Tasks

- [ ] Create resources. (moved to packages/filament-engagement)
- [ ] Create filters/actions. (moved to packages/filament-engagement)
- [ ] Create widgets. (moved to packages/filament-engagement)
- [ ] Create reusable relation managers. (moved to packages/filament-engagement)
- [x] Add feature flag for resource registration. (config exists)

### Acceptance

- [x] Filament resources can be disabled. (config flag exists)
- [x] Resources do not require Events package. (separate package)
- [x] Relation managers can be attached by Events/domain packages. (created in filament-engagement)

---

## Agent I — Tests

### Owns

```text
tests/*
```

### Tasks

- [ ] Migration tests. (not started)
- [ ] Model tests. (not started)
- [ ] Service tests. (not started)
- [ ] Events integration tests. (not started)
- [ ] Notification/reminder tests. (not started)
- [ ] Filament smoke tests. (not started)
- [ ] No-FK/no-soft-delete tests. (not started)

### Acceptance

- [ ] Full test suite passes. (no tests)
- [ ] Package works in isolation. (should work but not tested)
- [ ] Package works with fake Events integration. (not tested)
- [x] No domain-specific assumptions are present. (verified)
