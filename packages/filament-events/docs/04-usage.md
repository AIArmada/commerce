---
title: Usage
---

## Resources

### Events

`EventResource` manages event definitions with lifecycle actions on the list page, and Create/Edit forms for event configuration.

**Table columns:** title (searchable), status (badge, color-coded), visibility (badge), delivery_mode (badge), occurrences_count, published_at, updated_at.

**Filters:** status, visibility, delivery_mode.

**Lifecycle actions (table header):**
- **Publish** — promotes draft events to published (calls `EventLifecycleWorkflow::publish()`)
- **Archive** — archives published events
- **Cancel** — cancels an event with a reason

**Create/Edit form sections:**
- **Pricing & Registration** — `pricing_mode` (select: paid/free/mixed), `registration_mode` (select: required/optional/none), `issue_passes_for_free` (tri-state select)
- The inheritance chain is Event → Occurrence → Session; placeholder options indicate the fallback behavior at each level

**Infolist sections:** Identity, Lifecycle, Ownership, Metadata.

**Relation managers (on View page):** Occurrences, Sessions, Locations, Involvements, Registrations, Attendances. All read-only.

### Occurrences

`EventOccurrenceResource` manages scheduled runs with Create/Edit forms for occurrence-level pricing and registration overrides.

**Table columns:** event.title, title, starts_at (sortable), ends_at, status (badge), visibility (badge), capacity, published_at, cancelled_at.

**Filters:** status, visibility, event.

**Lifecycle actions:** Delay, Postpone, Cancel Occurrence, Complete.

**Create/Edit form overrides:** Same Pricing & Registration fields as Event, with values inherited from the parent event when left unset. Visibility also inherits from the parent event when left unset.
- Create forms also require the parent `event_id` and expose lifecycle defaults; edit forms keep the existing parent assignment unchanged.

### Sessions

`EventSessionResource` manages agenda items within occurrences, with Create/Edit forms for session-level overrides.

**Table columns:** event.title, occurrence.title, title, starts_at, ends_at, status (badge), visibility (badge), capacity, published_at, cancelled_at, sort_order.

**Filters:** status, visibility, event, occurrence.

**Lifecycle actions:** Delay, Postpone, Cancel Session, Complete.

**Create/Edit form overrides:** Same Pricing & Registration fields as Event/Occurrence, inherited from the parent occurrence/event when left unset. Sessions also require `starts_at` and `ends_at`, and visibility inherits from the parent occurrence when left unset.
- Create forms also require the parent `event_occurrence_id` and expose lifecycle defaults; edit forms keep the existing parent assignment unchanged.
- The lifecycle selector includes the same states as occurrences, including `rescheduled`.

**Relation managers:** Involvements, Locations, Registrations, Attendances, Materials.

### Venues

`VenueResource` manages physical venue/contact details.

**Table columns:** name (searchable), venue_type (badge), city, state, country, status (badge), created_at.

**Filters:** venue_type, status, country.

### Registrations

`EventRegistrationResource` provides visibility into registrations.

**Table columns:** registration_no (searchable, copyable), event.title, registrant_type (badge), registration_type (badge), status (badge), source (badge), total_participants, registered_at (sortable).

**Filters:** status, registration_type, source.

### Ticket administration

Ticket type, pass, holder, and transfer administration lives in `aiarmada/filament-ticketing`. Use that package's `TicketTypeResource`, `PassResource`, `PassHolderResource`, and `PassTransferResource` for generic ticketing CRUD across event and non-event ticketables.

### Attendance

`EventAttendanceResource` shows check-in records.

**Table columns:** event.title, occurrence.title, attendance_type (badge), checked_in_at (sortable), check_in_source (badge), attendee_type, attendee_id.

**Filters:** attendance_type, check_in_source.

## Check-In Console

The Check-In Console page (`/events/check-in`) provides:

- **Search by pass number or registration number** — via header action modal
- **Pass table** — shows pass_no, registration_no, registrant type, status, issued_at
- **Check In action** — delegates to `EventCheckInService::checkIn()` (visible for issued/active passes)
- **Walk-In Check-In** — header action with event select + attendee name/email

```php
use AIArmada\Events\Contracts\EventCheckInService;

app(EventCheckInService::class)->checkIn([
    'event_id' => $eventId,
    'event_occurrence_id' => $occurrenceId,
    'pass_id' => $pass->id,
    'attendance_type' => 'registered',
    'check_in_source' => 'qr',
]);
```

## Notification Center

The Notification Center page (`/events/notifications`) manages notification batches:

- **Table:** event.title, title, audience_scope (badge), status (badge), scheduled_at, sent_at
- **Row actions:** Send Now, Cancel, View Deliveries (modal)
- **Header action:** New Notification — create a pending batch with event, subject, audience scope

## Approval Queue

The Approval Queue page (`/events/approvals`) processes event submissions:

- **Table:** approvable_type (badge), approvable_id, status (badge), requested_by, assigned_to, created_at, approved_at, rejected_at
- **Row actions:** Approve (optional notes), Reject (required reason), Assign to Me

## Event Public Preview

The Event Public Preview page shows an event as the public would see it. Accessed via a link from the View Event page. Displays:

- Event details (title, summary, description, status, delivery_mode)
- Occurrences
- Speakers and organizers
- Pinned updates and notices
- Ticket types

## Owner Safety

All resources apply `OwnerUiScope::apply(..., includeGlobal: false)` to their main queries. This means:

- Tables only show records visible to the current owner
- Relationship selects only list records visible to the current owner
- Navigation badges are computed from owner-scoped queries

Server-side validation in action handlers still relies on the core events package.

## Disabling resources

Individual resources can be disabled via config:

```php
'resources' => [
    'enabled' => [
        'venue' => false,
    ],
],
```
