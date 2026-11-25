---
title: Troubleshooting
---

## Common Issues

### Legacy namespaces were removed

This release reorganizes the package into the Modular Dune layout. The old
top-level `Models`, `Enums`, `States`, `Actions`, `Services`, `Support`,
`Resolvers`, `Listeners`, `Observers`, `Policies`, `Jobs`, `Steps`,
`Checkout`, `Integrations`, and `Facades` directories under `AIArmada\Events`
no longer exist, the `Events` facade is gone, and the Spatie LaravelData DTOs
were replaced with `final readonly` PHP DTOs. Update imports before
upgrading:

| What you used | Use now |
|---|---|
| `Event`, `EventOccurrence`, `EventSession` models | `AIArmada\Events\Features\Catalog\Models\Event` (and `EventOccurrence` / `EventSession`), or `AIArmada\Events\Platform\ModelResolver::eventClass()` |
| `EventRegistration`, `EventRegistrationParticipant`, `EventRegistrationQuestion` | `AIArmada\Events\Features\Registrations\Models\*` |
| `EventAttendance`, `EventWalkIn`, `EventHeadcountLog` | `AIArmada\Events\Features\Attendance\Models\*` |
| `Venue`, `VenueSpace`, `EventLocation`, `FacilityType`, `VenueFacility` | `AIArmada\Events\Features\Venues\Models\*` |
| `EventTaxonomy`, `EventTerm`, `EventClassification` | `AIArmada\Events\Features\Taxonomy\Models\*` |
| `EventSubmission`, `EventEscalation`, `EventApprovalRequest` | `AIArmada\Events\Features\Governance\Models\*` |
| `EventChangeLog`, `EventUpdate` | `AIArmada\Events\Features\Changes\Models\*` |
| Status enums (for example a `RegistrationStatus` enum) | `spatie/laravel-model-states` state classes under `Features\...\States\*` |
| DTOs (for example `EventData`, `RegistrationData`) | `AIArmada\Events\Data\*` |
| `FacilityAvailability` enum under `Enums` | `AIArmada\Events\Data\FacilityAvailability` |
| `ModelResolver`, `EventWriteGuard`, `ScopesByEventOwner` under `Support` | `AIArmada\Events\Platform\*` |
| `IssueEventRegistrationPassesAction`, `RegisterForFreeAction`, … | `AIArmada\Events\Features\<Feature>\Actions\*` |
| `Events::…` facade calls | Resolve the contract from the container, for example `app(AIArmada\Events\Contracts\EventLifecycleWorkflow::class)` |

Public contracts now take and return scalar IDs (`string`) instead of Eloquent
models — update call sites that passed model instances.

### Money values appear 100 times too large or too small

Ticket prices and registration totals use integer minor units. For example,
`50000` with currency `MYR` represents MYR 500.00. Do not pass decimal
major-unit values.

### Migrations fail

Ensure the `events` database config key exists and the connection is valid.
Check `EVENTS_JSON_COLUMN_TYPE` — use `jsonb` for PostgreSQL or `json` for
SQLite.

### Search documents are not updating

Check that `EVENTS_SYNC_BUILD_SEARCH_DOCUMENTS=true` and that
`events.search.indexer` is not set to
`AIArmada\Events\Drivers\NullEventSearchIndexerDriver`. The default indexer
(`Features\Search\Actions\IndexEventSearchTargetAction`) covers events,
occurrences, and sessions.

If you changed event, occurrence, or session attribute, audience,
classification, or time-expression records and expected the search document
to move, make sure the relevant sync toggle is enabled:

- `EVENTS_SYNC_AUDIENCES_TO_FACETS`
- `EVENTS_SYNC_CLASSIFICATIONS_TO_FACETS`

### Owner scoping not working

Enable owner mode in config:

```
EVENTS_OWNER_ENABLED=true
```

Ensure your owner model implements the required `OwnerResolverInterface`
contract from `commerce-support`. The resolver must be bound in your service
provider.

### Registrations not creating passes

Verify the registration has associated registration items with valid
`ticket_type_id` references. Passes are created through explicit issuance
(`EventPassIssuanceService` / `IssueEventRegistrationPassesAction`), not
automatically on registration creation.

### Change notices are published but nobody is notified

`publishNotice()` only records the change and dispatches
`EventChangeNoticePublished`. Delivery runs in
`DispatchEventChangeNoticeNotifications`. Check that:

- an `EventChangeNoticeNotificationDispatcher` is bound (the built-in `Drivers\EventChangeNoticeNotificationDriver` requires `aiarmada/communications`)
- `events.change_notices.audience_resolver` resolves recipients for the audience scope the change log resolved to (`registrants` for `critical`/`high` impact, `followers` otherwise)
- a matching `EventUpdate` record exists on the change log — without one the dispatcher has no followers or notification content to work from

### Registration refuses creation

Check:

- The occurrence's `status` is in `lifecycle.occurrence.registration_accepting_statuses`
- The occurrence has not reached capacity
- The registration window is open
- `event_occurrence_id` references a valid occurrence

### `ModelResolver` throws "not configured"

A `RuntimeException` such as `The events model [event] is not configured`
means the requested key is `null` in `events.models` and no feature module
registered a default for it (for example, the owning feature was removed) or
the configured class does not extend `Illuminate\Database\Eloquent\Model`.
See [Configuration](03-configuration.md#model-overrides).

### Participant not appearing in occurrence/session queries

Ensure the participant was created with `event_occurrence_id` /
`event_session_id` set explicitly. Participants inherit scope from the parent
registration only when those columns are left null.

### Check-in fails

The registration must have status `confirmed` and the occurrence must be in a
check-in-accepting status (configured via
`lifecycle.occurrence.check_in_accepting_statuses`). When no occurrence is
supplied to `EventCheckInService::checkIn()`, the earliest
check-in-accepting occurrence is used; if none exists, check-in throws an
`InvalidArgumentException`.

### Model not found in Filament admin

The Filament resources apply `OwnerUiScope::apply(..., includeGlobal: false)`
by default. Global records are intentionally hidden. If you need to see
global records, check your `EVENTS_OWNER_INCLUDE_GLOBAL` setting and ensure
the resource allows it.

### Free registration returns zero passes

Free registrations created via `RegisterForFreeAction` do not have
registration items. Pass issuance handles this by ensuring a hidden
zero-priced `TicketType` for the resolved event scope, then issuing a single
generic pass. Verify `issue_passes_for_free` is enabled at the
event/occurrence/session level, or left `null` so it can inherit the parent
or configured default.

### `RegisterForFreeAction` throws `UseRecordWalkInActionException` or `UseRecordHeadcountActionException`

The event's `registration_mode` is `None`, and `open_door_mode` is set to
`walk_in` or `headcount`. Use `RecordWalkInAction` or
`RecordHeadcountLogAction` instead — both take a scalar scope
(`'event'`/`'occurrence'`/`'session'` plus an ID).

### `RegisterForFreeAction` throws `NotFreeEventException`

The event is not in `Free` or `Mixed` pricing mode. Change the pricing mode
or use the paid registration path (requires `aiarmada/ticketing` ticket types
and commerce checkout).

### `RegisterForFreeAction` throws `OpenDoorRegistrationBlockedException`

The scope is open-door with `block` mode; no registration is available at
all. Flip `open_door_mode` to `walk_in` or `headcount` if people should be
admitted and tracked.

### `PromoteInterestedToConfirmedAction` throws `NotInterestedRegistrationException`

The registration's status is not `Interested`. Only interested (optional)
registrations can be promoted.

### Enums replaced with state classes

Event, occurrence, registration, and moderation statuses use
`spatie/laravel-model-states` state classes under each feature's `States`
directory. If you were using enum references:

| Before | After |
|---|---|
| `EventStatus::Published->value` | `$event->status instanceof EventStatus\Published` or `$event->status->getValue() === 'published'` |
| `RegistrationStatus::Confirmed` | `RegistrationStatus\Confirmed::class` |
| `$model->status === 'published'` | `$model->status->getValue() === 'published'` or `$model->status instanceof EventStatus\Published` |

State class imports come from
`AIArmada\Events\Features\Catalog\States\EventStatus` (and
`OccurrenceStatus`), `AIArmada\Events\Features\Registrations\States\RegistrationStatus`,
and `AIArmada\Events\Features\Governance\States\EventModerationStatus`. Status
values stored in the database are unchanged. Allowed transitions are defined
in each state base class's `config()` method.

### `DefaultEventRegistrationScopeResolver` TypeError on explicit mode

When a session or occurrence has an explicit `pricing_mode` or
`registration_mode` column value, the model cast may already return the enum
instance. The resolver
(`AIArmada\Events\Features\Registrations\Queries\DefaultEventRegistrationScopeResolver`)
handles both cases (raw string and pre-cast enum). If you see
`TypeError: ::from()` in the stack trace, ensure your package version includes
the `instanceof` guard added in this feature.

### Venue facility write rejected

`VenueFacility` saves enforce place integrity and throw
`InvalidArgumentException` when the facility type id is missing, malformed,
or unknown, when neither venue nor space is set, when a place id is not a
valid UUID string or null (non-string values, empty strings, and malformed
UUID strings such as `bad-id` are rejected before any existence query, never
silently treated as null), when the referenced venue or space does not exist,
or when the venue does not own the space (a standalone template space
requires a null venue). Creating through `$space->facilities()` — or omitting
`venue_id`/passing null for a bound space — inherits the venue automatically;
an explicitly set venue must still match. Invalid `availability` values throw
`ValueError` through the native `FacilityAvailability` enum cast. See
[Venue Facilities](06-venue-facilities.md#place-integrity-guards).

### Venue space reparent rejected

Changing a persisted `VenueSpace::$venue_id` while facilities are attached is
rejected with `InvalidArgumentException`. Remove the space facilities first,
then move the space; a non-null new parent must exist. See
[Venue Facilities](06-venue-facilities.md#space-reparenting).

### Facility type deletion blocked

Deleting a `FacilityType` referenced by any `EventFacility` row is rejected
with `InvalidArgumentException` before any place value is touched, so event
facilities are never orphaned. Retire the type with `is_active=false` instead.
See [Venue Facilities](06-venue-facilities.md#deletion-cleanup).

### `composer arch:check` fails in this monorepo

Run it from the monorepo root or through `composer --working-dir=packages/events
arch:check`. The `arch:writes` script changes into the monorepo root before
running PHPStan, because Larastan boots the Laravel application relative to
the working directory — a package-local `vendor` symlink is neither required
nor wanted.

## A change notice was not delivered

Change notices publish via
`EventChangeNoticeWorkflow::publishNotice($changeLogId)` and deliver through
the `aiarmada/communications` manager. If recipients did not receive a
notice, check the `EventChangeLog` record exists, the audience resolver
returned recipients, each recipient has a mail destination (a valid `email`
attribute or `routeNotificationForMail()`), and the communications
outbox/logs for transport errors.
