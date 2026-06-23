---
title: Troubleshooting
---

## Common Issues

### Migrations fail

Ensure the `events` database config key exists and the connection is valid. Check `EVENTS_JSON_COLUMN_TYPE` — use `jsonb` for PostgreSQL and `json` for MySQL if your MySQL version does not support native JSONB.

### Search documents are not updating

Check that `EVENTS_SYNC_BUILD_SEARCH_DOCUMENTS=true` and that `events.search.indexer` is not bound to `NullEventSearchIndexer`. The built-in `EventSearchDocumentBuilder` is used when the indexer config is left empty and covers events, occurrences, and sessions.

If you changed event, occurrence, or session attribute, audience, classification, or time-expression records and expected the search document to move, make sure the relevant sync toggle is enabled:

- `EVENTS_SYNC_ATTRIBUTES_TO_METADATA`
- `EVENTS_SYNC_AUDIENCES_TO_METADATA`
- `EVENTS_SYNC_TIME_EXPRESSIONS_TO_METADATA`
- `EVENTS_SYNC_AUDIENCES_TO_FACETS`
- `EVENTS_SYNC_CLASSIFICATIONS_TO_FACETS`

### Owner scoping not working

Enable owner mode in config:

```
EVENTS_OWNER_ENABLED=true
```

Ensure your owner model implements the required `OwnerResolverInterface` contract from `commerce-support`. The resolver must be bound in your service provider.

### Registrations not creating passes

Verify the registration has associated `registration_items` with valid `event_ticket_type_id` references. Passes are created through explicit action, not automatically on registration creation.

### Registration refuses creation

Check:

- The occurrence's `status` is in `lifecycle.occurrence.registration_accepting_statuses`
- The occurrence has not reached capacity
- The registration window is open
- `event_occurrence_id` references a valid occurrence

### Participant not appearing in occurrence/session queries

Ensure the participant was created with `event_occurrence_id` / `event_session_id` set explicitly. Participants inherit scope from the parent registration only when those columns are left null.

### Check-in fails

The registration must have status `confirmed` and the occurrence must be in a check-in-accepting status (configured via `lifecycle.occurrence.check_in_accepting_statuses`).

### Model not found in Filament admin

The Filament resources apply `OwnerUiScope::apply(..., includeGlobal: false)` by default. Global records are intentionally hidden. If you need to see global records, check your `EVENTS_OWNER_INCLUDE_GLOBAL` setting and ensure the resource allows it.

### Free registration returns zero passes

Free registrations created via `RegisterForFreeAction` do not have registration items. The `DefaultEventPassIssuer` handles this by issuing a single pass per registration when items are empty. Verify `issue_passes_for_free` is enabled at the event/occurrence/session level, or left `null` so it can inherit the parent or configured default.

### `RegisterForFreeAction` throws `UseRecordWalkInActionException` or `UseRecordHeadcountLogActionException`

The event's `registration_mode` is `None`, and `open_door_mode` is set to `walk_in` or `headcount`. Use `RecordWalkInAction` or `RecordHeadcountLogAction` instead.

### `RegisterForFreeAction` throws `NotFreeEventException`

The event is not in `Free` or `Mixed` pricing mode. Change the pricing mode or use the paid registration path (requires ticket types and commerce checkout).

### `PromoteInterestedToConfirmedAction` throws `NotInterestedRegistrationException`

The registration's status is not `Interested`. Only interested (optional) registrations can be promoted.

### Enums replaced with state classes

Event status enums (`EventStatus`, `OccurrenceStatus`, `RegistrationStatus`, `EventModerationStatus`) have been replaced with `spatie/laravel-model-states` state classes under `States/`. If you were using enum references:

| Before | After |
|---|---|
| `EventStatus::Published->value` | `$event->status instanceof EventStatus\Published` or `$event->status->getValue() === 'published'` |
| `RegistrationStatus::Confirmed` | `RegistrationStatus\Confirmed::class` |
| `$model->status === 'published'` | `$model->status->getValue() === 'published'` or `$model->status instanceof EventStatus\Published` |

Status values stored in the database are unchanged. Allowed transitions are defined in each state base class's `config()` method.

### `DefaultEventRegistrationScopeResolver` TypeError on explicit mode

When a session or occurrence has an explicit `pricing_mode` or `registration_mode` column value, the model cast may already return the enum instance. The resolver handles both cases (raw string and pre-cast enum). If you see `TypeError: ::from()` in the stack trace, ensure your package version includes the `instanceof` guard added in this feature.
