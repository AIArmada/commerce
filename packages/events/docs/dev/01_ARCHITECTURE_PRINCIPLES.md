# 01 — Architecture Principles

## Goal

Build a generic Laravel Events package that can be used by many applications without forcing those applications to adopt one domain model.

The package must handle:

- events, occurrences, sessions, agendas, itineraries, and series;
- speakers, organizers, sponsors, moderators, volunteers, vendors, and partners;
- locations, venues, sublocations, shared spaces, facilities, coordinates, Google Maps, Waze, and directions;
- online, hybrid, and physical delivery;
- registrations for individuals, families, friends, and groups;
- event-specific ticket/access definitions, issued passes, seating, standing, VIP/premium/general sections;
- attendance/check-in, including walk-ins;
- materials, references, links, media, languages;
- visibility, lifecycle, cancellation, postponement, delay, rescheduling, change visibility, and notifications;
- public submissions, approval workflows, management/editor/owner/partner permission models;
- user interactions such as following, bookmarking, interested/going/maybe, reminders, and subscriptions.

The package must avoid application-specific assumptions. For example, it must not hardcode `masjid_id`, `ustaz_id`, `kitab_id`, `ajk_id`, `course_id`, or `hotel_id` into generic tables.

## Architecture layers

```text
1. Database tables
   Store event truth and queryable state.

2. Eloquent models
   Represent records and expose relationships.

3. Contracts
   Define capabilities that external domain models may implement.

4. Traits
   Provide reusable relationships and convenience methods.

5. Services
   Execute workflows safely: schedule, change, register, issue pass, check in, notify, approve.

6. Policies / permission resolvers
   Decide who can create, edit, approve, publish, manage, notify, or view.

7. Filament resources/pages/widgets
   Provide full administration and operational tooling.
```

## Generic vs domain-specific split

### Generic Events package owns

```text
Event mechanics
Scheduling and occurrences
Sessions and itineraries
Event locations
Event facilities
Event roles and involvements
Registration headers and participants
Event access policies
Ticket/access definitions
Issued event passes
Seating maps, sections, seats, holds, allocations
Attendance and logs
Materials and references
Links, media, languages
Audience and eligibility rules
Taxonomies and classifications
Time expressions
Change logs and public updates
Notification batches and deliveries
Submissions and approval requests
Series
Generic interaction tables if not provided by another package
Filament admin resources for all event mechanics
```

### Domain package owns

For ilmu360:

```text
Masjids
Masjid membership / AJK / committee roles
Speakers / ustaz / ustazah directory
Books / kitab / authors
Prayer time resolver
Islamic taxonomies and local terminology
Domain approval rules
Domain-specific importers and integrations
```

For a training app:

```text
Trainers
Courses
Certificates
Learning modules
Corporate clients
```

For a venue app:

```text
Hotels
Rooms
Ballrooms
Equipment
Venue-specific pricing
```

The generic Events package connects to these using polymorphic references and contracts.

## No-redundancy principles

Do not create multiple tables that mean the same thing.

Use one `event_involvements` table for:

```text
speaker
organizer
sponsor
host
partner
moderator
panelist
volunteer
vendor
person in charge
security
photographer
```

Use one `event_materials` table for resources used by the event.

Use one `event_references` table for resources cited or linked as supporting context.

Use one `event_locations` table for event/occurrence/session location assignment.

Use one `event_registrations` table as the registration header.

Use `event_registration_participants` for family/friend/group members.

Use `event_passes` for actual issued access.

Use `event_attendances` for actual check-in.

Do not use `event_speakers`, `event_sponsors`, `event_organizers`, `event_moderators`, etc. unless a host app intentionally builds a read model or reporting projection.

## Scope pattern

Any table that can apply to event, occurrence, or session should use this pattern:

```text
event_id = always filled
event_occurrence_id = nullable
event_session_id = nullable
```

Meaning:

```text
event_id filled, occurrence null, session null
= applies to the whole event

event_id filled, occurrence filled, session null
= applies to one occurrence/date/location

event_id filled, occurrence filled, session filled
= applies to one session/agenda item
```

Tables using this scope pattern:

```text
event_locations
event_facilities
event_involvements
event_ticket_types
event_materials
event_references
event_links
event_media
event_languages
event_audiences
event_audience_profiles
event_eligibility_rules
event_classifications
event_time_expressions
event_updates
event_change_logs
event_notification_batches
event_access_policies
event_itineraries
event_attributes
```

## Lifecycle principle

Use `status` for current lifecycle code and timestamp columns for important lifecycle transitions.

Example:

```text
status = published
published_at = 2026-06-12 09:00:00+08

status = cancelled
cancelled_at = 2026-06-12 11:30:00+08
status_reason = Speaker unavailable
```

Do not use booleans for lifecycle transitions:

```text
Bad: is_published
Good: published_at nullable + status

Bad: is_cancelled
Good: cancelled_at nullable + status

Bad: is_approved
Good: approved_at nullable + status
```

Booleans are acceptable only for stable feature/property flags:

```text
is_primary
is_featured
is_required
is_child_friendly
is_active
walk_in_allowed
registration_required
payment_required
seating_required
```

## No database-level coupling

No table should use database-level foreign key constraints or cascading deletes.

Use indexed UUID reference columns and application services to enforce integrity.

Why:

- The package must support polymorphic models across packages.
- Domain packages may have independent lifecycle and migration order.
- Multi-package monorepos often cannot rely on strict FK deployment order.
- Application-side deletion/archival rules are more explicit and safer.
- The package should remain portable across host apps.

This does not mean ignoring integrity. It means integrity is enforced through:

```text
validators
model policies
service methods
domain events
check commands
admin repair tools
tests
```

## Deletion principle

No soft deletes.

Do not create `deleted_at` columns.

Use lifecycle/status columns:

```text
archived_at
cancelled_at
revoked_at
voided_at
released_at
expired_at
unfollowed_at
removed_at
cancelled_at
rejected_at
```

For destructive deletion, use explicit application actions, authorization, and logs. The package should prefer archival/void/revoke state over record removal for business records.

## Timezone principle

Use `timestampTz()` for event time columns.

Use `timezone` string columns where the local timezone matters for display and recurrence/time expression resolution.

Important columns:

```text
starts_at timestampTz
ends_at timestampTz
timezone string
```

The application should store instants consistently, but still preserve event timezone for local display and special time resolution.

## Coordinates principle

Locations should support coordinates as first-class queryable data, not hidden metadata.

Required where applicable:

```text
latitude
longitude
geo_point
google_place_id
google_maps_url
waze_url
map_url
geocoded_at
geocoding_source
```

Coordinates enable:

```text
nearby events
radius search
distance sorting
routing
map display
state clustering
geo recommendations
```

## Change visibility principle

Important changes must be stored and shown clearly.

Do not rely on `updated_at`.

Use:

```text
event_change_logs = internal audit trail
event_updates = public/user-facing updates
event_update_items = before/after lines
event_notification_batches = notification campaigns
event_notification_deliveries = per-recipient delivery tracking
```

A speaker, topic, time, venue, cancellation, postponement, or live-link change must be visible to affected users.

## Public role vs admin permission

Do not confuse public event roles with admin permissions.

```text
event_involvements = public/event relationship
speaker, sponsor, organizer, host, moderator, panelist

event_management_assignments = admin/management permission
owner, editor, approver, viewer, partner_admin
```

A masjid may be a public organizer, while an AJK user may be an internal editor/approver for that masjid.

## Package extension principle

The package should expose contracts such as:

```php
HasEventAddress
CanOrganizeEvents
CanManageEventsFor
CanBeInvolvedInEvents
CanBeEventMaterial
CanBeEventReference
AcceptsEventSubmissions
ResolvesEventTimeExpression
```

Domain models implement these contracts.

The generic Events package should never need to know whether the model is `Masjid`, `Speaker`, `Kitab`, `Hotel`, `School`, or `Organization`.

## Checklist

- [x] Do not add domain-specific columns to generic event tables.
- [x] Use scope pattern for event/occurrence/session attachable records.
- [x] Use UUID primary keys everywhere.
- [x] Use `timestampTz()` / `timestampsTz()` appropriately.
- [x] Do not use soft deletes.
- [x] Do not add database foreign key constraints.
- [x] Do not add cascading deletes.
- [x] Use code columns and application constants for statuses/types.
- [x] Use lifecycle timestamps instead of lifecycle booleans.
- [x] Keep payment/order/checkout/invoice logic outside this package.
- [x] Keep public event roles separate from internal management permissions.
- [x] Use contracts/traits/services to solve behavior, not only tables.
