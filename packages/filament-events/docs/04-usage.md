---
title: Usage
---

## Resources

### Events

`EventResource` manages event definitions with lifecycle actions on the list page, and Create/Edit forms for event configuration. Record actions delegate to the core scalar-ID workflows: Publish and Archive/Cancel call `EventLifecycleWorkflow` (`publish($eventId)`, `archive('event', $eventId)`, `cancel('event', $eventId, $reason)`), and Clone calls `EventCloneService::cloneEvent($eventId, $options)` before redirecting to the new edit page.

**Table columns:** title (searchable), status (badge, color-coded by the state class), visibility (badge), delivery_mode (badge), occurrences_count, published_at, last_state_change_at, updated_at.

**Filters:** status, visibility, delivery_mode.

**Row actions:**
- **Publish** — promotes the event through `EventLifecycleWorkflow::publish()`
- **Archive** — archives the event
- **Cancel** — cancels the event with a reason
- **Clone** — duplicates the event through `EventCloneService` and redirects to the new edit page

The table header carries a single **Export Events** action using the built-in Filament exporter.

**Create/Edit form sections:**
- **Pricing & Registration** — `pricing_mode` (select: paid/free/mixed), `registration_mode` (select: required/optional/none), `issue_passes_for_free` (tri-state select)
- The inheritance chain is Event → Occurrence → Session; placeholder options indicate the fallback behavior at each level

Create pages also expose a **From Template** header action (`CreateEvent::getHeaderActions()`), which calls `EventTemplateService::createFromTemplate($templateId)` and redirects to the created event.

**Infolist sections:** Identity, Lifecycle, Ownership, Metadata.

**Relation managers (on View page):** Occurrences, Sessions, Locations, Involvements, Registrations, Attendances, Classifications. Additional managers can be appended via `resources.event_relation_managers`.

### Occurrences

`EventOccurrenceResource` manages scheduled runs with Create/Edit forms for
occurrence-level pricing and registration overrides. The create page delegates
to `CreateEventOccurrenceAction` (so `ends_at` defaulting and normalization
match the domain layer), and record actions call `EventLifecycleWorkflow`
with scalar IDs: `delay('occurrence', $id, $reason, $expectedStartsAt)`,
`postpone('occurrence', $id, $reason)`, `cancel('occurrence', $id, $reason)`,
`complete('occurrence', $id)`, and `cloneOccurrence($id)`.

**Table columns:** event.title, title, starts_at (sortable), ends_at, status (badge), visibility (badge), capacity, published_at, cancelled_at.

**Filters:** status, visibility, event.

**Create/Edit form overrides:** Same Pricing & Registration fields as Event, with values inherited from the parent event when left unset. Visibility also inherits from the parent event when left unset. Create forms also require the parent `event_id`; edit forms keep the existing parent assignment unchanged.

### Sessions

`EventSessionResource` manages agenda items within occurrences, with
Create/Edit forms for session-level overrides. The create page delegates to
`CreateEventSessionAction`; record actions call `EventLifecycleWorkflow`
(`delay('session', …)`, `postpone`, `cancel`, `complete('session', $id)`) and
`EventCloneService::cloneSession($id)`.

**Table columns:** event.title, occurrence.title, title, starts_at, ends_at, status (badge), visibility (badge), capacity, published_at, cancelled_at, sort_order.

**Filters:** status, visibility, event, occurrence.

**Create/Edit form overrides:** Same Pricing & Registration fields as Event/Occurrence, inherited from the parent occurrence/event when left unset. Sessions also require `starts_at` and `ends_at`, and visibility inherits from the parent occurrence when left unset. Create forms also require the parent `event_occurrence_id`; edit forms keep the existing parent assignment unchanged.

**Relation managers:** Involvements, Locations, Registrations, Attendances, Materials.

### Venues

`VenueResource` manages physical venue/contact details. Address fields are
resolved from the venue's canonical primary `HasAddresses` attachment. Venue
and venue-space resources are global catalogs: their queries are not owner
scoped, matching the core package's intentional shared vocabularies.

**Table columns:** name (searchable), venue_type (badge), city, state, country, status (badge), created_at.

**Filters:** venue_type, status.

### Registrations

`EventRegistrationResource` provides visibility into registrations.

**Table columns:** registration_no (searchable, copyable), event.title, registrant_type (badge), registration_type (badge), status (badge), source (badge), total_participants, registered_at (sortable).

**Filters:** status, registration_type, source.

### Ticket administration

Ticket type, pass, holder, and transfer administration lives in
`aiarmada/filament-ticketing`. Use that package's `TicketTypeResource`,
`PassResource`, `PassHolderResource`, and `PassTransferResource` for generic
ticketing CRUD across event and non-event ticketables.

### Attendance

`EventAttendanceResource` shows check-in records.

**Table columns:** event.title, occurrence.title, attendance_type (badge), checked_in_at (sortable), check_in_source (badge), attendee_type, attendee_id.

**Filters:** attendance_type, check_in_source.

## Check-In Console

The Check-In Console page (`/events/check-in`) provides:

- **Search by pass number or registration number** — via header action modal
- **Pass table** — shows pass_no, registration_no, registrant type, status, issued_at
- **Check In action** — delegates to `EventCheckInService::checkInWithResult()`, visible only for passes where `Pass::isValid()` is true
- **Walk-In Check-In** — header action with event select + attendee name/email

```php
use AIArmada\Events\Contracts\EventCheckInService;

app(EventCheckInService::class)->checkInWithResult([
    'event_id' => $eventId,
    'event_occurrence_id' => $occurrenceId,
    'pass_id' => $passId,
    'attendance_type' => 'registered',
    'check_in_source' => 'qr',
]);
```

Both check-in actions require the event `manageAdmissions` permission. The console uses `checkInWithResult()` so duplicate scans resolve to the
existing attendance instead of erroring.

## Approval Queue

The Approval Queue page (`/events/approvals`) processes event submissions
through the scalar-ID `EventApprovalWorkflow`:

- **Table:** approvable_type (badge), approvable_id, status (badge), requested_by_type, assigned_to_type, created_at, approved_at, rejected_at
- **Row actions:** Approve (optional notes → `approve($requestId, $actor, $notes)`), Reject (required reason → `reject($requestId, $actor, $reason, $notes)`), Assign to Me (requires an authenticated user model)

## Event Public Preview

The Event Public Preview page shows an event as the public would see it. Accessed via a link from the View Event page, at `events/public-preview/{eventId}`. Mounting without an id, with a malformed or unknown id, or with a cross-owner id aborts with 404 (missing, malformed, and cross-owner ids are indistinguishable, so the URL never leaks event existence). Displays:

- Event details (title, summary, description, status, delivery_mode)
- Occurrences
- Speakers and organizers
- Pinned updates and notices
- Ticket types

The page uses `VerifiesRecordOwnerContext` (from `commerce-support`,
guarding its `event` prop): the event is re-verified against the current
owner scope on every Livewire request, and a mid-session owner change clears
it instead of rendering stale cross-owner data. See
[Multi-tenancy](../../commerce-support/docs/14-multi-tenancy.md#livewire-record-components)
for the shared contract.

## Owner Safety

Event-bound resources (`EventResource`, occurrence, session, registration,
participant, attendance, change log, template) apply
`OwnerUiScope::apply(..., includeGlobal: false)` to their main queries. This
means:

- Tables only show records visible to the current owner
- Relationship selects only list records visible to the current owner
- Navigation badges are computed from owner-scoped queries

Venue, venue-space, taxonomy, and term resources are intentional global
catalogs and are not owner scoped, matching the core package's ownership
model.

Server-side validation in action handlers still relies on the core events
package: lifecycle, clone, moderation, and check-in calls all run through
owner-guarded scalar-ID contracts.

## Embedding events on host resources

`AIArmada\FilamentEvents\RelationManagers\EventsRelationManager` embeds the event table
on any host resource whose model has an `events` relationship. Register it from the
host resource:

```php
use AIArmada\FilamentEvents\RelationManagers\EventsRelationManager;

public static function getRelations(): array
{
    return [EventsRelationManager::class];
}
```

The table renders from `EventResource`, and create/edit actions link to the event
resource pages.

## Disabling resources

Individual resources can be disabled via config:

```php
'resources' => [
    'enabled' => [
        'venue' => false,
    ],
],
```
