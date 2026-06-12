---
title: Usage
---

## Resources

All resources are read-only views. Data entry is handled through the companion `aiarmada/events` domain package.

### Events

`EventResource` manages event definitions with lifecycle actions on the list page.

**Table columns:** title (searchable), status (badge, color-coded), visibility (badge), delivery_mode (badge), occurrences_count, published_at, updated_at.

**Filters:** status, visibility, delivery_mode.

**Lifecycle actions (table header):**
- **Publish** — promotes draft events to published (calls `EventLifecycleWorkflow::publish()`)
- **Archive** — archives published events
- **Cancel** — cancels an event with a reason

**Infolist sections:** Identity, Lifecycle, Ownership, Metadata.

**Relation managers (on View page):** Occurrences, Sessions, Locations, Involvements, Registrations, Ticket Types, Attendances. All read-only.

### Occurrences

`EventOccurrenceResource` manages scheduled runs.

**Table columns:** event.title, title, starts_at (sortable), ends_at, status (badge), visibility (badge), capacity, published_at, cancelled_at.

**Filters:** status, visibility, event.

**Lifecycle actions:** Delay, Postpone, Cancel Occurrence, Complete.

### Sessions

`EventSessionResource` manages agenda items within occurrences.

**Table columns:** event.title, occurrence.title, title, starts_at, ends_at, status (badge), sort_order.

**Filter:** status.

### Venues

`VenueResource` manages physical venue/contact details.

**Table columns:** name (searchable), venue_type (badge), city, state, country, status (badge), created_at.

**Filters:** venue_type, status, country.

### Registrations

`EventRegistrationResource` provides visibility into registrations.

**Table columns:** registration_no (searchable, copyable), event.title, registrant_type (badge), registration_type (badge), status (badge), source (badge), total_participants, registered_at (sortable).

**Filters:** status, registration_type, source.

### Ticket Types

`EventTicketTypeResource` manages admission definitions.

**Table columns:** event.title, name, code (badge), access_type (badge), price (money), currency, quota, status (badge), sales_starts_at, sales_ends_at.

**Filters:** access_type, status.

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
    'event_pass_id' => $pass->id,
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

Individual resources can be disabled via config or plugin methods:

```php
// Via config
'resources' => [
    'enabled' => [
        'venue' => false,
    ],
],

// Via plugin
FilamentEventsPlugin::make()
    ->resources(['venue' => false]);
```
