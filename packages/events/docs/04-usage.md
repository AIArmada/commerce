---
title: Usage
---

This guide uses the Modular Dune public surface: scalar-ID `Contracts`, feature
`Api` interfaces, immutable `Data` DTOs, and config-driven model resolution.
See [Troubleshooting](99-troubleshooting.md#legacy-namespaces-were-removed) for
the legacy-name mapping.

## Updating custom Events integrations

- Custom `EventOrderDriver` implementations supply `ownerType` and `ownerId`
  when constructing `EventRefundMatchData`. See [refund authorization](#refund-and-admissions-authorization).
- Custom `RegistrationsApi` implementations return a list of child registration
  IDs from `expandComponents()`. Return an empty list when there are no children.
- Link bundle components to an event, occurrence, or session within the parent
  registration's event. Expansion rejects components outside that event scope.
- Set `OwnerContext` before creating registrations or reading event scopes.
  Registration payloads cannot assign owners through `owner_type` or `owner_id`.
- Use `EventTicketingDriver::restoreRefundedRegistrationItemInventory()` with the
  registration item ID for refund restocking. The order-level
  `restoreRefundedInventory()` method has been removed.
- Use `EventSearchPayloadResolver` and `EventSearchRelationProvider` to customize
  automatic indexing. A custom `EventSearchDocumentBuilder` binding applies to
  callers that resolve that contract directly.

## Resolving models

Application code that may run with model overrides resolves classes through
`ModelResolver`:

```php
use AIArmada\Events\Platform\ModelResolver;

$eventClass = ModelResolver::eventClass();
$occurrenceClass = ModelResolver::occurrenceClass();
$sessionClass = ModelResolver::sessionClass();
$registrationClass = ModelResolver::registrationClass();
$venueClass = ModelResolver::venueClass();

$event = $eventClass::query()->findOrFail($eventId);
```

The 23 keys are: `event`, `occurrence`, `session`, `registration`,
`registration_participant`, `registration_question`, `attendance`, `walk_in`,
`headcount_log`, `submission`, `escalation`, `approval_request`,
`management_assignment`, `change_log`, `update`, `search_document`, `venue`,
`venue_space`, `location`, `facility`, `classification`, `attribute`,
`audience`. See [Configuration](03-configuration.md#model-overrides).

The rest of this guide imports the default concrete classes for brevity; when
your app overrides `events.models.*`, resolve through `ModelResolver` instead.

## Creating Events

### Free events

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Data\RegistrationMode;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Ticketing\Data\PricingMode;

$event = OwnerContext::withOwner($owner, function (): Event {
    return Event::query()->create([
        'title' => 'Free Workshop',
        'status' => 'draft',        // NOT NULL
        'visibility' => 'public',   // NOT NULL
        'pricing_mode' => PricingMode::Free,
        'registration_mode' => RegistrationMode::Required,
        'issue_passes_for_free' => true,
    ]);
});
```

`status` and `visibility` are `NOT NULL` with no database default, so every
create must set both.

### Primary classifications

An event, occurrence, or session can have only one primary classification
within its own scope. Saving a primary `EventClassification` clears the other
classifications in that same scope, so direct model writes and Filament
relation-manager edits keep the invariant consistent
(`Features\Taxonomy\Actions\DemotePrimaryClassification` backs the direct
path).

Use factory states for convenience in tests and seeders:

```php
Event::factory()->free()->published()->create();
Event::factory()->freeWithOptionalRegistration()->published()->create();
Event::factory()->freeOpenDoor()->published()->create();
Event::factory()->mixed()->published()->create();
```

### Inheritance chain

Pricing and registration modes follow this resolution chain:

1. Session override → Occurrence override → Event override → config default / auto-derive
2. Accessors `effectivePricingMode()` and `effectiveRegistrationMode()` walk the chain and return the resolved value
3. `issue_passes_for_free` is nullable at every level: an explicit `true`/`false` wins, and `null` inherits occurrence, session, then event, then `events.features.free_only.auto_issue_passes_for_free`
4. With ticket-type derivation disabled, an occurrence/session without a local `pricing_mode` still inherits the explicit parent pricing mode instead of falling straight to `Paid`

### Per-level overrides

Sessions and occurrences can override the parent's mode:

```php
use AIArmada\Events\Features\Catalog\Actions\CreateEventSessionAction;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Ticketing\Data\PricingMode;

$occurrence = EventOccurrence::query()->findOrFail($occurrenceId);

$session = app(CreateEventSessionAction::class)->handle($occurrence, [
    'title' => 'Premium Workshop',
    'status' => 'scheduled',     // NOT NULL
    'visibility' => 'public',    // NOT NULL
    'pricing_mode' => PricingMode::Paid,     // Override free event
    'registration_mode' => 'required',
    'issue_passes_for_free' => false,        // Explicit false overrides inherited true
    'capacity' => 50,
]);
```

Creating an occurrence or session with these keys left absent (or explicitly
`null`) preserves inheritance instead of writing a concrete default.

This allows mixed-mode setups where a free event has a paid add-on session,
or vice versa. Visibility follows the same event → occurrence → session
fallback when a create flow leaves it unset.

### Scopes

```php
use AIArmada\Events\Features\Catalog\Models\Event;

Event::query()->free()->get();                // pricing_mode = Free
Event::query()->mixed()->get();               // pricing_mode = Mixed
Event::query()->openDoor()->get();            // registration_mode = None
Event::query()->published()->get();           // status = published

// Eager load for N+1-safe accessors
Event::query()->withResolvedModes()->get();   // eager-loads ticket types for effectivePricingMode()
```

### Visibility

Use `hidden` when an event, occurrence, or session must be excluded from
public discovery and event search indexing. It remains available to
administrative queries, while the `public()` scope continues to return only
public events.

```php
$event->update(['visibility' => 'hidden']);

Event::query()->public()->get(); // excludes hidden events
```

### Sharing

```php
$event->shareTitle();
$event->shareUrl();          // uses events.shares.route_name
$event->shareDescription();  // null when empty after normalization
$event->shareImage();        // cover conversion URL when media exists
```

## Event lifecycle

Status transitions are handled by `spatie/laravel-model-states`. Use the
workflow contract to transition with side effects; pass **scalar target
types** (`'event'`, `'occurrence'`, `'session'`) and IDs:

```php
use AIArmada\Events\Contracts\EventLifecycleWorkflow;

$workflow = app(EventLifecycleWorkflow::class);

$workflow->publish($eventId);                                            // records published_at, dispatches EventPublished
$workflow->postpone('event', $eventId, 'Venue unavailable');
$workflow->delay('occurrence', $occurrenceId, 'Doors delayed', now()->addHour());
$workflow->cancel('session', $sessionId, 'Speaker illness');
$workflow->archive('event', $eventId);
$workflow->complete('occurrence', $occurrenceId);

$rescheduledId = $workflow->reschedule('occurrence', $occurrenceId, $newStart, $newEnd);
$workflow->reschedule('session', $sessionId, $newStart, null, ['timezone' => 'Asia/Kuala_Lumpur']);
```

Workflow rules:

- `delay` and `reschedule` reject the `'event'` target type; `archive` rejects `'session'`.
- Live occurrences and sessions cannot be rescheduled.
- `reschedule` returns the rescheduled target ID, accepts an explicit `null`
  end for open-ended schedules, and rejects an end at or before the start.
- Every transition records a change through `ChangesApi`, so updates and
  change notices stay in sync.

Direct model transitions without domain events:

```php
use AIArmada\Events\Features\Catalog\States\EventStatus;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus;
use AIArmada\Events\Features\Registrations\States\RegistrationStatus\RegistrationStatus;

$event->status->transitionTo(EventStatus\Published::class);
$occurrence->status->transitionTo(OccurrenceStatus\Cancelled::class);
$registration->transitionStatus(RegistrationStatus::Confirmed::class);
```

Use the workflow contract when domain events and change logs are needed.
`transitionStatus()` records a registration lifecycle timestamp, while
calling `$registration->status->transitionTo(...)` intentionally bypasses
that bookkeeping. Allowed transitions are defined in each state base class's
`config()` method.

## Managing Occurrences

```php
use AIArmada\Events\Features\Catalog\Actions\BatchCreateOccurrencesAction;
use AIArmada\Events\Features\Catalog\Actions\CreateEventOccurrenceAction;
use AIArmada\Events\Features\Catalog\Actions\DeleteEventSessionAction;
use AIArmada\Events\Features\Catalog\Actions\UpdateEventOccurrenceAction;
use AIArmada\Events\Features\Catalog\Actions\UpdateEventSessionAction;
use AIArmada\Events\Features\Catalog\Models\Event;

$event = Event::query()->findOrFail($eventId);

$occurrence = app(CreateEventOccurrenceAction::class)->handle($event, [
    'title' => 'KL Conference Day 1',
    'starts_at' => now()->addDays(30),
    'ends_at' => now()->addDays(30)->addHours(8),
    'timezone' => 'Asia/Kuala_Lumpur',
    'status' => 'scheduled',
    'capacity' => 300,
    'visibility' => 'public',
]);

app(UpdateEventOccurrenceAction::class)->handle($occurrence, ['capacity' => 350]);

// Batch scheduling
app(BatchCreateOccurrencesAction::class)->handle($event, [
    ['title' => 'Day 1', 'starts_at' => $day1Start, 'ends_at' => $day1End],
    ['title' => 'Day 2', 'starts_at' => $day2Start, 'ends_at' => $day2End],
]);

app(DeleteEventSessionAction::class)->handle($session);
```

Occurrences represent the actual scheduled run of an event. They carry their
own status lifecycle and capacity. Registration acceptance follows the
occurrence status configured in `events.lifecycle.occurrence.registration_accepting_statuses`;
paid ticket sales additionally follow each ticket type's `sales_starts_at` and
`sales_ends_at` window.

`CreateEventOccurrenceAction` defaults an omitted `ends_at` to two hours
after the start; pass an explicit `null` for an open-ended occurrence.
`CreateEventSessionAction` defaults an omitted `ends_at` to one hour after
the start. Session slugs are generated from the title and uniquified within
the event (`keynote`, `keynote-2`, ...), backed by a unique `event_id` +
`slug` index. `UpdateEventOccurrenceAction` and `UpdateEventSessionAction`
keep `ends_at` when the key is omitted, clear it when explicitly `null`, and
return a changed-attribute array (empty when nothing changed). Content
inputs are normalized server-side before persistence (titles trimmed with
collapsed whitespace, blank summaries/descriptions stored as `null`).

### Primary occurrence sync

`SyncPrimaryEventOccurrenceAction` creates or updates the primary occurrence
(earliest by start time) with lifecycle protection:

```php
use AIArmada\Events\Data\ScheduleKind;
use AIArmada\Events\Features\Catalog\Actions\SyncPrimaryEventOccurrenceAction;

$occurrence = app(SyncPrimaryEventOccurrenceAction::class)->handle($event, [
    'schedule_kind' => ScheduleKind::Single,
    'starts_at' => $startsAt, // DateTimeInterface|string, stored immutable
    'ends_at' => $endsAt,     // omit to keep/default, null for open-ended
    'timezone' => 'Asia/Kuala_Lumpur',
]);
```

Published, postponed, delayed, and rescheduled occurrences are rescheduled
through `EventLifecycleWorkflow` so the transition is recorded, including
open-ended schedules: an explicit `null` end stays unknown through the
lifecycle transition. An omitted `ends_at` keeps the stored end for
comparisons and for the workflow call. Requested non-schedule attributes
(title, visibility, and so on) are applied after a successful lifecycle
reschedule. Terminal occurrences (cancelled, completed, archived) and live
occurrences refuse schedule changes with an `InvalidArgumentException`;
re-syncing identical values is a no-op that never re-transitions but still
applies non-schedule attributes. Re-rescheduling an already-rescheduled
occurrence saves the new dates and records another change without an illegal
self-transition. Date strings are parsed in the resolved timezone (attribute,
occurrence, event, then `events.defaults.timezone`) and stored as UTC
instants. Writes are owner-guarded through `EventWriteGuard`, and lifecycle
guards re-resolve the persisted occurrence or session before checking the
owning event so dirty in-memory `event_id` values cannot bypass the owner
boundary.

### Cloning and templates

```php
use AIArmada\Events\Contracts\EventCloneService;
use AIArmada\Events\Contracts\EventTemplateService;

$cloneId = app(EventCloneService::class)->cloneEvent($eventId);          // string
$cloneId = app(EventCloneService::class)->cloneOccurrence($occurrenceId);
$cloneId = app(EventCloneService::class)->cloneSession($sessionId);

$eventId = app(EventTemplateService::class)->createFromTemplate($templateId, [
    'title' => 'Ramadan Lecture (March)',
]);
```

Cloning delegates scope work to the `events.clone-scope` extension slot, so
each feature clones exactly the records it owns.

## Free registration

```php
use AIArmada\Events\Features\Registrations\Actions\RegisterForFreeAction;

$registrationIds = app(RegisterForFreeAction::class)->execute(
    'occurrence',
    $occurrenceId,
    participants: [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'is_primary' => true],
    ],
);
```

`execute()` accepts a scalar scope (`'event'`, `'occurrence'`, or `'session'`
plus an ID) and returns a `list<string>` of registration IDs — one per
participant. Every participant produces one registration.

Exceptions (all under `AIArmada\Events\Exceptions`):

| Thrown | When |
|---|---|
| `NotFreeEventException` | Scope is not `Free`/`Mixed` pricing |
| `OpenDoorRegistrationBlockedException` | Open door with `block` mode |
| `UseRecordWalkInActionException` | Open door with `walk_in` mode |
| `UseRecordHeadcountActionException` | Open door with `headcount` mode |
| `EventRegistrationNotAvailableException` | Scope is not accepting registrations |
| `EventCapacityExceededException` | Remaining capacity is lower than the participant count |

### Optional registrations (Interested → Confirmed)

For events with `registration_mode = Optional`:

```php
use AIArmada\Events\Features\Registrations\Actions\PromoteInterestedToConfirmedAction;
use AIArmada\Events\Features\Registrations\Actions\RegisterForFreeAction;

// Creates Interested registrations (no pass issued)
$registrationIds = app(RegisterForFreeAction::class)->execute(
    'occurrence',
    $occurrenceId,
    participants: [['name' => 'Bob']],
    options: ['with_pass' => false],
);

// Promote to confirmed + issue pass later
app(PromoteInterestedToConfirmedAction::class)->execute($registrationIds[0]);
```

Promotion requires remaining capacity for the registration's full
`total_participants` and throws `NotInterestedRegistrationException` when the
status is not `Interested`.

Waitlisted registrations can be promoted through
`PromoteWaitlistedRegistrationsAction`:

```php
use AIArmada\Events\Features\Registrations\Actions\PromoteWaitlistedRegistrationsAction;

app(PromoteWaitlistedRegistrationsAction::class)->handle($registrationId);

// Or promote a whole scope in waitlist order
$promoted = app(PromoteWaitlistedRegistrationsAction::class)->handleScope(
    'occurrence',
    $occurrenceId,
);
```

Promotion resolves and locks the parent scope first, re-checks lifecycle
eligibility and capacity under that lock, and preserves FIFO order.
`handleScope()` stops at the first request that no longer fits instead of
promoting later requests ahead of it.

### Idempotent free checkout

Retried checkout deliveries can pass an idempotency key; retries with the
same key and participant count return the original batch instead of creating
duplicates:

```php
app(RegisterForFreeAction::class)->execute(
    'occurrence',
    $occurrenceId,
    participants: [['name' => 'Alice', 'is_primary' => true]],
    options: ['idempotency_key' => 'checkout-attempt-1'],
);
```

## Open-door events (walk-in / headcount)

For events with `registration_mode = None`:

Walk-in and headcount writes also enforce the canonical lifecycle policy:
the event must not be cancelled/completed/archived/expired/voided, an
occurrence scope must be in `walk_in_accepting_statuses`, and a session scope
must be in `registration_accepting_statuses`.

```php
use AIArmada\Events\Features\Attendance\Actions\RecordHeadcountLogAction;
use AIArmada\Events\Features\Attendance\Actions\RecordWalkInAction;

// Walk-in: record attendance without a registration
$walkInId = app(RecordWalkInAction::class)->execute(
    'occurrence',
    $occurrenceId,
    count: 3,
    recordedBy: $user,          // optional Model
    notes: 'Front desk check-in',
);

// Headcount: just increment a counter
$headcountLogId = app(RecordHeadcountLogAction::class)->execute(
    'occurrence',
    $occurrenceId,
    count: 5,
    intervalLabel: '10:00-10:15',
    recordedBy: $user,
    notes: 'Late arrivals from shuttle bus',
);
```

Both actions return the created record ID, enforce the scope's open-door mode
(`NotOpenDoorEventException`, `WrongOpenDoorModeException`), and clamp the
count to a minimum of 1.

## Registrations

### Registration service

```php
use AIArmada\Events\Contracts\RegistrationServiceInterface;

$registrationId = app(RegistrationServiceInterface::class)->register([
    'event_id' => $eventId,
    'event_occurrence_id' => $occurrenceId,
    'registrant_type' => $user->getMorphClass(),
    'registrant_id' => $user->getKey(),
    'registration_type' => 'individual',
    'status' => 'pending',
    'source' => 'website',
    'total_participants' => 2,
    'participants' => [
        [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+60123456789',
            'is_primary' => true,
            'status' => 'registered',
        ],
        [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'relationship_to_registrant' => 'spouse',
            'is_primary' => false,
            'status' => 'registered',
        ],
    ],
    'items' => [
        [
            'ticket_type_id' => $ticketTypeId,
            'quantity' => 2,
            'unit_price' => 50000,
            'total_price' => 100000,
        ],
    ],
]);
```

`register()` returns the new registration ID. Participant `email`, `phone`,
and `company` values are written as `aiarmada/contacting` contact methods.

### Registration lifecycle

All lifecycle methods take registration IDs:

```php
$service = app(RegistrationServiceInterface::class);

$service->approve($registrationId, $actor);            // pending → confirmed (+ welcome notification)
$service->cancel($registrationId, 'No-show', $actor);  // any → cancelled
$service->reject($registrationId, 'Duplicate', $actor);
$service->waitlist($registrationId);
$service->complete($registrationId);
$service->refund($registrationId, 'Requested');
$service->markRefundPending($registrationId, 'Awaiting gateway');
$service->restoreFromRefundPending($registrationId);
```

Pass issuance runs under the registration row lock and only for statuses in
`events.lifecycle.registration.pass_issuance_statuses` (default `confirmed`,
`checked_in`, `completed`); cancelled, refunded, rejected or expired
registrations receive no passes. Quantities across every registration item
that shares a ticket type are summed before issuing, including the ticket
type's `admits_quantity`. Checkout records issued and delivered pass IDs
separately in step data, so a retried checkout only delivers passes that have
not already been delivered while still retrying any failed delivery.

### Refund and admissions authorization

`refund()` and `restoreFromRefundPending()` run against a row-locked fresh
copy of the registration inside a transaction, so duplicate deliveries
(retried order webhooks, double provider events) serialize per registration
instead of both passing the status check: the second delivery sees the
already-refunded state and becomes a no-op. This matters because the
`EventRegistrationRefunded` listeners have real side effects — ticket
inventory restore, seat release, and pass revocation would otherwise run
twice. Keep refund-family transitions on `RegistrationServiceInterface`;
reimplementing them without the lock reintroduces double-restore.

For order-backed refunds use the commerce path, which creates the provider
refund and drives the state machine inside the event's verified owner
context. The self-service deadline uses the primary occurrence start time
when the registration is event-level; `refund_failed` restores payment state
only when the registration was genuinely `refund_pending`.

Asynchronous provider confirmations match against the provider and refund
identifiers before entering the matched order refund's owner context.
Custom order drivers supply the matched refund's owner tuple in
`EventRefundMatchData::ownerType` and `ownerId`. Both fields are null for
global refunds or when no single refund matched.

```php
use AIArmada\Events\Features\Commerce\Actions\ProcessEventRegistrationRefundAction;

$result = app(ProcessEventRegistrationRefundAction::class)->handle(
    eventId: $eventId,
    registrationId: $registrationId,
    actor: $user,
    organizerOverride: false,
    reason: 'Requested by attendee',
);
// EventRefundResultData { status, transactionId, paymentId, message, refundId }
```

### Authorization defaults

`EventRegistrationPolicy::requestRefund()` allows the polymorphic registrant
or a manager resolved through the event/owner boundary; every other actor is
denied. `EventPolicy::manageAdmissions()` allows the event owner/manager only.
Both abilities deny by default, so a host that needs a different refund or
admissions policy must override the policy class through Laravel's Gate
registration rather than widening the package default.

### Reading registrations

Cross-feature and UI code reads registrations through `RegistrationsApi`:

```php
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;

$registrations = app(RegistrationsApi::class);

$summary = $registrations->summarize($registrationId);            // ?RegistrationSummary
$participants = $registrations->participantsForRegistration($registrationId);
$orderItems = $registrations->orderItemsForRegistration($registrationId);
$passIds = $registrations->passIdsForRegistration($registrationId);
$remaining = $registrations->capacityRemaining($eventId, $occurrenceId, $sessionId, $capacity);
```

`RegistrationSummary`, `EventRegistrationParticipantData`, and
`EventRegistrationOrderItemRef` are immutable DTOs — no Eloquent models cross
the API boundary.

### Registration input validation

`register()` validates its payload before writing: the occurrence and session
must belong to the event, every item's ticket type must exist in the owner
scope and belong to the same event, money stays non-negative integer minor
units, registrant and participant morphs must resolve to existing models,
participant emails must be valid, and bundle parents must belong to the same
event. Forged `id` and scope columns on items, participants, and answers are
stripped. Each collection (items, participants, answers) is capped at 1000
entries per registration.

### Scoped participants

Participants can be scoped directly to an occurrence or session by setting
`event_occurrence_id` / `event_session_id` on the participant payload. They
inherit scope from the parent registration only when those columns are left
null.

## Check-in and Attendance

```php
use AIArmada\Events\Contracts\EventCheckInService;

$checkIn = app(EventCheckInService::class);

$attendance = $checkIn->checkIn([
    'event_id' => $eventId,
    'event_occurrence_id' => $occurrenceId,
    'event_registration_id' => $registrationId,
    'event_registration_participant_id' => $participantId,
    'pass_id' => $passId,
    'attendance_type' => 'registered',
    'check_in_source' => 'qr',
    'verified_by_user_id' => $staffUserId,   // UUID
    'notes' => 'Gate 2',
]);

$checkIn->checkOut($attendance->id, $actor);
$checkIn->cancelCheckIn($attendance->id, 'Duplicate scan', $actor);
```

Both `checkIn()` and `checkInWithResult()` return immutable `Data` records:
`checkInWithResult()` returns `EventCheckInResult` (with a `created` flag so
duplicate scans are detectable), `checkIn()` unwraps to `AttendanceRecord`.

Validation highlights: `event_id` is required; attendee type/id must both be
present or both null; `verified_by_user_id` must be a UUID; notes are capped
at 5000 characters; metadata must be an array; `performed_by` type/id must
both be present or both null. Registrations must be in a
`check_in_allowed_statuses` lifecycle state, selected passes must be valid,
and an explicitly supplied occurrence must be in a check-in-accepting
status. When no occurrence is supplied, the earliest occurrence in a
check-in-accepting status is used, and a missing occurrence throws. Anonymous
walk-ins without a registration, participant, pass, or attendee identity
always create a new attendance instead of collapsing into an earlier one.
Every mutation runs through `EventWriteGuard` and writes an
`EventAttendanceLog` entry.

### Attendance lookups

```php
use AIArmada\Events\Features\Attendance\Api\AttendanceApi;

$attendance = app(AttendanceApi::class);

$hasEvidence = $attendance->hasAttendanceEvidence($registrationId);
$registrationId = $attendance->registrationIdForAttendance($attendanceId);
```

## Managing Venues

```php
use AIArmada\Addressing\Features\AddressLifecycle\Models\Address;
use AIArmada\Events\Features\Venues\Models\Venue;

$venue = Venue::query()->create([
    'name' => 'MATRADE Hall',
    'slug' => 'matrade-hall',
    'venue_type' => 'convention_center',
    'status' => 'active',
    'visibility' => 'public',
]);

$address = Address::query()->create([
    'line1' => 'Jalan Sultan Haji Ahmad Shah',
    'city' => 'Kuala Lumpur',
    'state' => 'WP Kuala Lumpur',
    'country_code' => 'MY',
]);

$venue->attachAddress($address, type: 'primary', isPrimary: true);
```

Venues without a physical address, such as digital or virtual venues, simply
omit the attachment. The canonical attachment is the write path, and venue
and event-location consumers read from the canonical primary address:

```php
$primary = $venue->primaryAddress();
$primary = $location->primaryAddress();
```

VenueSpace supports both reusable catalog spaces (`venue_id = null`) and
venue-owned spaces (`venue_id` set). Slugs are unique within their ownership
scope: catalog slugs are globally unique, while venue-owned slugs are unique
per venue. Event locations store an optional `space_name_snapshot` so
consumers can preserve the selected name when the live space is renamed.

Venue catalog (`VenueSpace`, `VenueSpaceType`, `FacilityType`,
`VenueFacility`) is unowned by design; `Venue` is also unowned.
`EventLocation` and `EventFacility` are event-scoped via
`ScopesByEventOwner`. All seven use only addressing `HasAddresses`.

Place facilities are catalog values attached to venues and spaces, separate
from event attributes and event-scoped facilities. See
[Venue Facilities](06-venue-facilities.md) for the catalog, seed action,
CRUD, filtering, typed availability, global-write rules, and capacity
semantics.

## Managing Ticket Types

```php
use AIArmada\Ticketing\Features\TicketTypes\Actions\EnsureTicketTypeAction;

$ticketType = app(EnsureTicketTypeAction::class)->handle($occurrence, [
    'name' => 'Early Bird',
    'code' => 'EARLY',
    'access_type' => 'entry',
    'price' => 50000,
    'currency' => 'MYR',
    'max_quantity' => 10, // per-purchase cap
    'status' => 'active',
    'visibility' => 'public',
    'sales_starts_at' => now()->subMonth(),
    'sales_ends_at' => $occurrence->starts_at,
]);
```

`EnsureTicketTypeAction` whitelists attributes explicitly, and `ticket_types`
has no `quota` or `capacity` column — anything outside that whitelist is
silently dropped. Per-scope capacity lives on the occurrence/session, and
remaining-quota checks count registration item quantity across
capacity-blocking statuses. Events-facing code can use
`Features\Commerce\Actions\EnsureTicketTypeForOccurrenceAction` with a scalar
scope (`'event'`, `'occurrence'`, `'session'`).

Server-derived cart identity attributes (`purchasable_type`,
`purchasable_id`, `ticket_type_id`, event scope IDs, `code`, participants, and
inventory keys) always win over `extraAttributes`; extra keys that do not
collide are preserved as extension fields.

## Managing Involvements

Involvements link people (any model) to an event, occurrence, or session with
a role. The `involveable` is polymorphic — it can be a `User`,
`EventOrganizer`, or any model implementing `CanBeInvolvedInEvents`.

### Syncing scoped involvements

`SyncEventInvolvementsAction` replaces the involvement rows owned by one
scope. The scope is re-resolved from persisted storage (never trusted from
dirty in-memory attributes) and the owning event is owner-guarded, so a
forged child model cannot redirect writes across events. Rows outside the
scope are never touched, and organizer rows are preserved unless
`'organizer'` is explicitly included in the role filter — organizer rows in
the input are rejected without that opt-in. Every row must use a role inside
the explicit filter, and an explicit empty filter is rejected instead of
deleting more broadly. Every row is validated before any delete runs, role
codes resolve in one batched query (unknown or inactive codes are rejected),
and duplicate identities are collapsed per scope and role.

```php
use AIArmada\Events\Features\Catalog\Actions\SyncEventInvolvementsAction;

app(SyncEventInvolvementsAction::class)->handle(
    $session, // Event, EventOccurrence, or EventSession
    [
        ['role_code' => 'speaker', 'involveable_type' => 'person', 'involveable_id' => $personId],
        ['role_code' => 'moderator', 'display_name' => 'Guest host', 'visibility' => 'private'],
    ],
    ['speaker', 'moderator'],
);
```

### Organizers

An organizer is just an involvement with `role_code = 'organizer'`:

```php
$event->involvements()->create([
    'involveable_type' => $user->getMorphClass(),
    'involveable_id' => $user->getKey(),
    'role_code' => 'organizer',
    'is_primary' => true,
    'visibility' => 'public',
    'status' => 'confirmed',
]);
```

### Scoped languages and classifications

`SyncEventLanguagesAction` and `SyncEventClassificationsAction` accept the
same event/occurrence/session scope and only replace rows owned by that
scope. Event-level syncs carry a null occurrence and session, and a nullable
session occurrence is preserved as null rather than cast to an empty string.

```php
use AIArmada\Events\Features\Catalog\Actions\SyncEventLanguagesAction;
use AIArmada\Events\Features\Taxonomy\Actions\SyncEventClassificationsAction;

app(SyncEventLanguagesAction::class)->handle($session, ['ms', 'ar']);
app(SyncEventClassificationsAction::class)->handle($session, ['topic' => ['Fiqh']]);
```

### Organizer vs createdBy

Don't confuse organizers (involvements) with `createdBy`:

- **`createdBy`** (`created_by_type` / `created_by_id` on the `events` table) — who created the event record. A single morphTo, set on creation, not publicly displayed.
- **Organizers** (`event_involvements` with `role_code = 'organizer'`) — people publicly credited as organizers. Multiple per event, with visibility, prominence, and display name control.

A created-by user might also be listed as an organizer, but they're stored
independently. Organizers are the public-facing list; `createdBy` is an audit
trail.

### The `CanOrganizeEvents` contract

Any model attached as an organizer involvement can implement
`CanOrganizeEvents` to control its display name, profile URL, and default
public visibility:

```php
use AIArmada\Events\Contracts\CanOrganizeEvents;
use AIArmada\Events\Traits\CanOrganizeEvents as OrganizesEvents;

class User extends Model implements CanOrganizeEvents
{
    use OrganizesEvents; // provides sensible defaults

    public function eventOrganizerName(): string
    {
        return $this->name;
    }

    public function eventOrganizerProfileUrl(): ?string
    {
        return route('profile.show', $this);
    }
}
```

## Owner-Scoped Queries

When `events.features.owner.enabled` is `true`, event roots use `HasOwner`.
Event-bound children inherit the event boundary through
`Platform\ScopesByEventOwner` (the `event_owner` global scope), series and
template children inherit their root boundary, submissions use their target
owner before conversion, and polymorphic workflow records require an
owner-safe parent:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Catalog\Models\Event;

// Automatically scoped to the current owner
$myEvents = Event::query()->get();

// Explicit owner context
OwnerContext::withOwner($masjid, function (): void {
    Event::query()->create([...]);
});

// Explicit global context for global records
OwnerContext::withOwner(null, function (): void {
    Event::query()->globalOnly()->get();
});

// Without owner scope (explicit cross-tenant opt-out)
Event::query()->withoutOwnerScope()->get();
```

`Platform\EventWriteGuard::findOrFail($eventId)` is the canonical write-path
resolver: it enforces the owner scope (or returns the plain lookup when
owner scoping is disabled) before any child mutation. Package code resolves
the event first and never trusts caller-supplied parent models.
`CatalogApi::describeScope()` and `RegistrationsApi::lock()` obey the active
owner context by default; trusted cross-owner system paths must opt out
explicitly rather than widening ordinary reads.

## Working with Passes

```php
use AIArmada\Events\Contracts\EventPassIssuanceService;

$passes = app(EventPassIssuanceService::class)->issueForRegistration($registrationId);
```

Free registrations created via `RegisterForFreeAction` do not have
registration items; issuance ensures a hidden zero-priced ticket type for the
resolved scope and issues a single generic pass. The concrete
`Features\Commerce\Actions\IssueEventRegistrationPassesAction` also exposes
`handle(string $registrationId, bool $respectFreePassPolicy = false)`.

## Seating Integration

When `aiarmada/seating` is installed and a `TicketType` has a `seating_mode`,
passes trigger automatic seat allocation on issue:

- **Assigned**: a specific seat is held, then converted to an allocation on pass issuance.
- **General Admission**: a section-level allocation (no specific seat) is created, capped by section capacity.
- **None**: no allocation is created.

Pass revocation (cancel/refund/void/expire) automatically releases the
associated seat allocation through `Commerce` listeners — no manual release
needed.

### Feature flags

| Config key | Default | Description |
|---|---|---|
| `events.features.auto_allocate_seats` | `true` | Allocate seats on pass issuance |
| `events.features.auto_revoke_passes_on_cancel` | `true` | Revoke passes when registration is cancelled |

## Selling Tickets via Commerce Checkout

When `aiarmada/cart`, `aiarmada/checkout`, and `aiarmada/orders` are
installed, ticket types can be sold through the standard commerce checkout
pipeline alongside products.

### Adding ticket types to cart

```php
use AIArmada\Events\Features\Commerce\Actions\AddEventTicketTypeToCartAction;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;

$ticketType = TicketType::query()->findOrFail($ticketTypeId);

app(AddEventTicketTypeToCartAction::class)->handle(
    cart: app(AIArmada\Cart\Runtime\CartManagerInterface::class)->getCartInstance('default'),
    ticketType: $ticketType,
    quantity: 2,
    participants: [
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        ['name' => 'Bob', 'email' => 'bob@example.com'],
    ],
);
```

The action validates status, visibility, sales windows, min/max quantity, and
inventory (when configured) before adding. It handles cart merging — if the
same ticket type is already in the cart, quantities and participants are
merged rather than overwritten. Session-scoped ticket types preserve
`event_session_id` in the cart item attributes.

### Mixed carts (tickets + products)

Ticket types and products can coexist in the same cart. The checkout pipeline
runs pricing, discounts, shipping, tax, and payment uniformly. After order
creation, `CreateEventRegistrationsStep` (registered as a checkout step
contributor) processes event ticket items by resolving the matching event,
occurrence, or session scope from each `TicketType`, then creates
registrations and passes. Product items flow through normal order fulfillment.

### Participant data

Participant data is stored in the cart item's `attributes.participants` array.
The step resolves it in priority order:

1. Participants from the cart item attributes (passed via `AddEventTicketTypeToCartAction`)
2. Fallback to the order customer's name/email/phone
3. Generic "Attendee #N" entries

One participant entry produces one registration with one ticket item —
matching the order item's quantity.

### Quota validation

Scope capacity is checked through `RegistrationsApi::capacityRemaining()` (and
`Features\Registrations\Queries\RegistrationCapacityQuery`): the minimum of
the applicable finite occurrence and session remainings, where each remaining
is configured `capacity` minus the summed `total_participants` of
registrations in capacity-blocking statuses (`pending`, `confirmed`,
`refund_pending`, `checked_in`). A `null` capacity means unbounded and is
ignored in the minimum. Session registrations carry the parent
`event_occurrence_id`, so sibling sessions and direct occurrence bookings all
consume the same occurrence aggregate. Capacity-sensitive writes lock event,
then occurrence, then session inside the transaction before checking. In a
`Mixed` scope, order lines for genuinely free tickets (zero catalog price)
always take the canonical free path with scope-capacity enforcement, even
when paid-capacity enforcement is off; a paid line discounted to zero keeps
paid semantics. Promoting an `interested` registration requires remaining
capacity for its full `total_participants`. Quota is not checked during
checkout intent (re-entering checkout for an existing registration). The
inventory package is not required; ticket capacity is self-contained.

### Checkout intent resolver

`DefaultEventCheckoutIntentResolver` is bound when both cart and checkout
packages are available. It creates a dedicated cart instance for the
registration, preserving participant data:

```php
use AIArmada\Events\Features\Commerce\Actions\StartOccurrenceCheckoutAction;

$session = app(StartOccurrenceCheckoutAction::class)->handle(
    'occurrence',      // or 'session'
    $occurrenceId,
    $registrationId,
);
// Returns CheckoutSession from the commerce pipeline, or null when unavailable
```

The resolver binding itself requires the Orders fulfillment integration;
without the checkout pipeline the null resolver is bound and the action
returns null instead of a session.

Override via config `events.integrations.checkout_intent_resolver` or by
binding `EventCheckoutIntentResolver`.

### Per-scope ticket types

When a ticket type is created against an `EventOccurrence` or `EventSession`,
it is scoped to that specific occurrence or session. Both
`AddEventTicketTypeToCartAction` (via attributes) and
`CreateRegistrationsFromOrderAction` (via validation) enforce scope matching
through `Platform\EventTicketScope`. Ticket types created against the parent
`Event` remain event-wide and can be registered for any matching scope.

### Agent sales

```php
use AIArmada\Events\Features\Commerce\Actions\RecordAgentTicketSaleAction;

$result = app(RecordAgentTicketSaleAction::class)->handle(
    ticketTypeId: $ticketTypeId,
    quantity: 2,
    agent: $agent,
    customerData: ['name' => 'Walk-up Buyer', 'email' => 'buyer@example.com'],
);
```

## Extensibility via Contracts

The package exposes contracts for every major operation. Bind your own
implementation to override default behavior:

```php
use AIArmada\Events\Contracts\EventRegistrationEligibility;
use AIArmada\Events\Contracts\EventSearchPayloadResolver;

app()->bind(EventSearchPayloadResolver::class, MySearchResolver::class);
app()->bind(EventRegistrationEligibility::class, MyRegistrationEligibility::class);
```

Registration actions call `EventRegistrationEligibility` before creating
registrations. The default implementation rejects occurrences whose status is
not listed in `events.lifecycle.occurrence.registration_accepting_statuses`.
Bind a custom implementation when the host application needs additional
access, publication, or entitlement rules.

Available contracts include `RegistrationServiceInterface`,
`EventLifecycleWorkflow`, `EventCheckInService`, `EventCloneService`,
`EventTemplateService`, `EventModerationWorkflow`, `EventSubmissionConverter`,
`EventTaxonomyHierarchy`, `EventPassIssuanceService`,
`EventRefundEligibility`, `EventRegistrationEligibility`,
`EventRegistrationScopeResolver`, `EventSearchEngine`, `EventSearchIndexer`,
`EventSearchPayloadResolver`, `EventDisplayTimezoneResolver`,
`EventScheduleResolver`, `EventEngagementManager`, and more.

### ExtensionRegistry slots

`Platform\ExtensionRegistry` resolves classes declared in feature manifests:

```php
use AIArmada\Events\Contracts\EventScopeCascadeDeleter;
use AIArmada\Events\Platform\ExtensionRegistry;

$deleters = app(ExtensionRegistry::class)->resolve('events.cascade-delete', EventScopeCascadeDeleter::class);
$cloners = app(ExtensionRegistry::class)->resolve('events.clone-scope');
```

- `events.cascade-delete` — invoked when an event/occurrence/session subtree is deleted.
- `events.clone-scope` — invoked by the clone service when cloning a scope.

Package features declare their extensions in `feature.json`; applications can
add their own by binding around the registry in a provider.

## Search Indexing

When `events.sync.build_search_documents` is enabled, the package maintains
`event_search_documents` automatically through the built-in search indexer for
events, occurrences, and sessions.

```php
use AIArmada\Events\Contracts\EventSearchDocumentBuilder;

$builder = app(EventSearchDocumentBuilder::class);
$document = $builder->buildForEvent($eventId);
$occurrenceDocument = $builder->buildForOccurrence($occurrenceId);
$sessionDocument = $builder->buildForSession($sessionId);
```

The generated payload includes the target content and relation-backed facets
for audiences and classifications when those sync flags are enabled. Opaque
metadata is included only as diagnostic context and is never used as a source
for promoted fields.

To customize indexed data, use the `EventSearchPayloadResolver` or
`EventSearchRelationProvider` contracts. The built-in indexer passes its
loaded target directly to the package document builder.

Set `events.search.queue_indexing=true` to queue rebuilds instead of writing
them synchronously (the `BuildEventSearchDocumentJob` carries owner context
and is unique per target). To disable automatic indexing entirely, point
`events.search.indexer` at
`AIArmada\Events\Drivers\NullEventSearchIndexerDriver`.

### Search relation loading

The package's default `EventSearchRelationProvider` supplies only
package-owned eager-loading paths. Applications that render richer event
cards can configure a provider that composes the package default and adds
host-owned relations:

```php
use AIArmada\Events\Contracts\EventSearchRelationProvider;
use AIArmada\Events\Platform\DefaultEventSearchRelationProvider;

final class EventCardRelationshipProvider implements EventSearchRelationProvider
{
    public function relations(): array
    {
        return [
            ...(new DefaultEventSearchRelationProvider)->relations(),
            'institution.media',
            'venue.addresses',
        ];
    }
}
```

Bind it via `events.search.relation_provider`. This provider is used by
search adapters for eager loading only. It does not own matching thresholds,
event fields, statuses, or search-index integration.

### Search engine

```php
use AIArmada\Events\Contracts\EventSearchEngine;

$results = app(EventSearchEngine::class)->search([
    'search' => 'conference',
    'visibility' => 'public',
    'status' => 'published',
    'limit' => 25,
]);
```

The default `EloquentEventSearchEngine` returns an owner-scoped Eloquent
collection. Its default visibility filter excludes hidden events. For public
listings, pass both `visibility => 'public'` and `status => 'published'`
as shown above. Set `events.search.engine` to plug in an external index.

## Dispatching an event change notice

Publishing a notice goes through the workflow contract, which records the
change and dispatches `EventChangeNoticePublished`. Delivery is a separate
listener step, reached by binding `EventChangeNoticeNotificationDispatcher`:

```php
use AIArmada\Events\Contracts\EventChangeNoticeWorkflow;

app(EventChangeNoticeWorkflow::class)->publishNotice($changeLogId, [
    'reason' => 'Venue unavailable',
]);

app(EventChangeNoticeWorkflow::class)->retractNotice($changeLogId);
```

The built-in `AIArmada\Events\Drivers\EventChangeNoticeNotificationDriver`
resolves recipients through the bound `EventChangeNoticeAudienceResolver` and
sends an `EventChangeNoticeNotification` through `aiarmada/communications`.
The audience scope comes from the change log's `impact_level`: `critical` or
`high` targets `registrants`, anything else targets `followers`.

You can also record a change directly through the feature API:

```php
use AIArmada\Events\Features\Changes\Api\ChangesApi;

app(ChangesApi::class)->dispatchChange(
    eventId: $eventId,
    changeType: 'venue_changed',
    changeCategory: 'administration',
    impactLevel: 'high',
    requiresNotification: true,
    reason: 'Main hall flooded',
);
```

## Deleting events

Deleting an event cascades through the owned subtree in application logic:
occurrences, sessions, registrations (with participants, answers, items,
attendances, and passes), locations, media, classifications, updates,
submissions, and the remaining event-scoped records, plus the event's ticket
types and seat maps. Deleting an occurrence or session cascades through its
own scope the same way. The cascade runs through the `events.cascade-delete`
`ExtensionRegistry` slot, so each feature's extension deletes exactly the
tables it owns. Cross-owner deletes are blocked by the owner write guards
before the cascade runs.

## Event submissions

`EventSubmission.target_type` / `target_id` is the owner target the submission
is filed under, resolved through the `target()` morph. It is never the
submitted event or session: converted or container-bound submissions point at
the created graph through `event_id`, `event_occurrence_id`, and
`event_session_id` instead.

```php
use AIArmada\Events\Features\Governance\Models\EventSubmission;

$submission = EventSubmission::query()->create([
    'submitter_type' => $submitter->getMorphClass(),
    'submitter_id' => $submitter->getKey(),
    'target_type' => $owner->getMorphClass(),
    'target_id' => $owner->getKey(),
    'submission_data' => [
        'title' => 'Ramadan Lecture',
        'description' => 'Event description',
        'starts_at' => '2026-07-01 09:00:00',
        'ends_at' => '2026-07-01 10:00:00',
    ],
    'status' => 'pending',
    'submitted_at' => now(),
]);
```

Writes are guarded by `Platform\EventSubmissionOwnerScope`: the target pair
must both be present or both null, persisted targets are immutable, and
event-bound submissions must pass the event write guard. Graph integrity runs
on every save even when owner scoping is disabled or the submission is
global: `event_id` is required when an occurrence or session is linked, a
linked occurrence must exist and belong to the submitted event, a linked
session must exist and belong to the submitted event, and a supplied
occurrence must match the session's occurrence (a null session occurrence
never matches a non-null supplied occurrence). A null submission occurrence
with a session is allowed and preserves the canonical null. Deletes only
enforce the owner boundary and never block cleanup on a missing parent.
Session deletes cascade to linked submissions.

### Moderation workflow

```php
use AIArmada\Events\Contracts\EventModerationWorkflow;
use AIArmada\Events\Contracts\EventSubmissionConverter;

$moderation = app(EventModerationWorkflow::class);

$moderation->submit($submissionId, $actor);
$moderation->approve($submissionId, $actor, 'Looks good');
$moderation->requestChanges($submissionId, $actor, 'Add a venue', 'See notes');
$moderation->reject($submissionId, $actor, 'Duplicate');

// Approve + convert into a real event graph
$eventId = app(EventSubmissionConverter::class)->convert($submissionId);
```

Conversion normalizes title/summary/description content and creates the
event and its primary occurrence.

### Assignment requests and escalations

```php
use AIArmada\Events\Features\Governance\Actions\ApproveAssignmentRequestAction;
use AIArmada\Events\Features\Governance\Actions\SubmitAssignmentRequestAction;
use AIArmada\Events\Features\Governance\Api\GovernanceApi;

$request = app(SubmitAssignmentRequestAction::class)->handle(
    manageable: $event,
    requestor: $user,
    justification: 'Regional manager coverage',
    requestedRole: 'manager',
);

$assignment = app(ApproveAssignmentRequestAction::class)->handle($request, $reviewer, 'manager', 'Approved');

// Resolve open escalations for an event (through the scalar-ID API)
app(GovernanceApi::class)->resolveEscalations($eventId);
```

Approved assignments mirror to the authz integration through the bound
`EventAuthzDriver`.

## Finalizing event orders

Order finalization runs on the event path, not from the console. When a
registration is checked in,
`SyncEventOrderCompletionOnRegistrationCheckedIn` dispatches
`SyncEventOrderCompletionAction`, which fulfils every order item through
`FulfillEventOrderItemAction`. Payment and cancellation transitions
(`SyncEventOrderRegistrationsOnFulfillment`, `…OnOrderCanceled`,
`…OnOrderRefunded`, `…OnOrderRefundFailed`) drive the same fulfilment for
order-backed registrations.

Per-scope finalization is available to applications through the action,
which must run inside a resolved owner context:

```php
use AIArmada\Events\Features\Commerce\Actions\FinalizeOccurredEventOrdersAction;

app(FinalizeOccurredEventOrdersAction::class)->handle('occurrence', $occurrenceId);
```

Item fulfilment is delegated to the bound
`EventOrderItemFulfillmentResolver`. The package default resolves contact
details and returns them without persisting; bind your own resolver to write
them.

## Ownership model

7 models use `HasOwner` directly (`Event`, `EventSeries`, `EventTemplate`,
`EventOrganizer`, `EventItinerary`, `EventHeadcountLog`, `EventWalkIn`). 44
models inherit the event boundary via `Platform\ScopesByEventOwner`
(`event_owner` scope). The remaining records stay unscoped by design:
catalog/pivot/submission exceptions (`EventRole`, `EventTaxonomy`,
`EventTerm`, `EventTermPolicy`, `EventSeriesItemPivot`, `EventSubmission`)
plus the venue catalog (`Venue`, `VenueSpace`, `VenueSpaceType`,
`FacilityType`, `VenueFacility`).

```php
use AIArmada\Events\Platform\EventWriteGuard;

$event = EventWriteGuard::findOrFail($eventId);
```

Write paths resolve the event first via `EventWriteGuard::findOrFail()`, which
enforces the owner scope before mutating children.

The venue and taxonomy catalogs are intentional global vocabularies shared
across owners. Writes to them from tenant flows run in explicit global
context; the admin surface owns write authorization for these tables.
Authorization beyond the query layer lives in domain policies: `EventPolicy`
plus `EventOccurrencePolicy`, `EventSessionPolicy`,
`EventRegistrationPolicy`, and `EventSubmissionPolicy`, all registered by
their feature modules. Management abilities resolve through the event owner
(via the `CanManageEventsFor` contract). Public event visibility covers the
event, its occurrences, and sessions only: registration and submission
records stay visible to managers and to the polymorphic record owner
(registrant or submitter), never to unrelated viewers.

## Query service

```php
use AIArmada\Events\Features\Catalog\Queries\EventQueryService;

$query = app(EventQueryService::class);

$events = $query->findPublished();            // 100 rows, clamped to 500
$events = $query->findUpcoming(10);
$events = $query->findByOwner($owner, 50);
$event = $query->findBySlug('annual-conference-2026'); // earliest on duplicate slugs
```

`findPublished()`, `findUpcoming()`, and `findByOwner()` default to 100 rows
and clamp to 500. The search engine defaults to 25 rows and clamps to 100.

## Order to registration to pass sequence

```php
use AIArmada\Events\Features\Commerce\Actions\CreateRegistrationsFromOrderAction;
use AIArmada\Events\Features\Commerce\Actions\IssueEventRegistrationPassesAction;

$registrationIds = app(CreateRegistrationsFromOrderAction::class)->handle(
    'occurrence',        // scope type
    $occurrenceId,       // scope id
    $orderItemId,
    $participants,
    $purchaser,
);

app(IssueEventRegistrationPassesAction::class)->handle($registrationIds[0]);
```

Canonical paid flow: order item → `CreateRegistrationsFromOrderAction`
(scope + eligibility + `RegistrationServiceInterface::register` + component
expansion) → pass issuance through the ticketing driver. Free order items
reuse `RegisterForFreeAction` with `defer_pass_issuance` before the same pass
step.

## Ticketing DTOs and delivery

```php
use AIArmada\Ticketing\Features\TicketTypes\Actions\ExpandTicketTypeComponentsAction;
use AIArmada\Ticketing\Data\PassData;
use AIArmada\Ticketing\Data\TicketTypeData;

$dto = app(\AIArmada\Ticketing\Features\TicketTypes\Queries\MapTicketTypeData::class)->handle($ticketType);
$passDto = app(\AIArmada\Ticketing\Features\Passes\Queries\MapPassData::class)->handle($pass);
$components = app(ExpandTicketTypeComponentsAction::class)->handle($ticketType);
```

Use the canonical ticketing DTOs and `ExpandTicketTypeComponentsAction` (also
used by `CreateEventComponentRegistrationsAction`). Delivery goes through the
communications manager; cross-package links stay as `EventReference` rows
with content remaining in events.

## Content sync

```php
use AIArmada\Events\Features\Catalog\Actions\SynchronizeEventContent;

app(SynchronizeEventContent::class)->handle($event);
```

`Platform\EventContentNormalizer` is canonical for title/summary/description
normalization (titles trimmed with collapsed whitespace, blank summaries and
descriptions stored as `null`).
