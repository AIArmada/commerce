# 04 — Contracts, Traits, and Services

This document defines the code-level extension system. The database stores truth; contracts and services make the package reliable and extensible.

The generic Events package must not know domain-specific classes such as `Masjid`, `Kitab`, `Ustaz`, `Hotel`, or `School`. Domain models plug in by implementing contracts.

---

# A. Model capability contracts

## 1. `HasEventAddress`

For models that can provide an address/location to an event.

```php
interface HasEventAddress
{
    public function eventLocationName(): string;

    public function eventAddress(): ?array;

    public function eventCoordinates(): ?array;

    public function eventMapLinks(): ?array;

    public function eventDirections(): ?string;

    public function toEventLocationSnapshot(): array;
}
```

Use cases:

```text
Masjid address used as venue
Organization office used as event location
Mall store used as event location
School/hotel/venue provides address
```

Expected snapshot keys:

```text
name
line1
line2
city
state
postcode
country
latitude
longitude
google_place_id
google_maps_url
waze_url
map_url
directions
```

---

## 2. `HasEventCoordinates`

For models with coordinates.

```php
interface HasEventCoordinates
{
    public function eventLatitude(): ?float;

    public function eventLongitude(): ?float;

    public function eventGeoPoint(): mixed;
}
```

---

## 3. `HasEventMapLinks`

For models that can provide navigation links.

```php
interface HasEventMapLinks
{
    public function eventGoogleMapsUrl(): ?string;

    public function eventWazeUrl(): ?string;

    public function eventMapUrl(): ?string;
}
```

---

## 4. `CanBeGeocodedForEvents`

For models/locations that can be geocoded.

```php
interface CanBeGeocodedForEvents
{
    public function eventGeocodingAddress(): ?string;

    public function markEventGeocoded(array $result): void;
}
```

---

## 5. `HasEventSpaces`

For models that can expose concrete or shared spaces.

```php
interface HasEventSpaces
{
    public function eventSpaces(): iterable;

    public function availableEventSpaceTypes(): iterable;
}
```

Use cases:

```text
Masjid: main hall, Muslimah hall, parking lot
Hotel: ballroom, seminar room, lobby
Mall: centre court, entrance, lot number
```

---

## 6. `ProvidesEventFacilities`

For entities that can provide facility info.

```php
interface ProvidesEventFacilities
{
    public function eventFacilities(): iterable;
}
```

---

## 7. `CanOrganizeEvents`

For public organizer entities. Implemented by any model attached as an organizer involvement (`event_involvements.role_code = 'organizer'`). The involvement system reads these methods to display organizer info on public event pages — they are not used for storage or querying.

```php
interface CanOrganizeEvents
{
    public function eventOrganizerName(): string;

    public function eventOrganizerProfileUrl(): ?string;

    public function shouldBePublicOrganizerByDefault(): bool;
}
```

Maps to:

```text
event_involvements.role_code = organizer
```

The `CanOrganizeEvents` trait provides sensible defaults:
- `eventOrganizerName()` returns `$this->name ?? $this->title`
- `eventOrganizerProfileUrl()` returns `$this->profile_url`
- `shouldBePublicOrganizerByDefault()` returns `true`

These are read when rendering an involvement card or listing (e.g. on the event detail page). They are not called during create/update — the involvement stores its own display state via `visibility` and `is_featured` columns.

---

## 8. `OwnsEvents`

For entities that administratively own events.

```php
interface OwnsEvents
{
    public function ownedEvents();

    public function defaultEventVisibility(): string;

    public function defaultEventApprovalRequired(): bool;
}
```

Maps to:

```text
events.owner_type
events.owner_id
```

---

## 9. `CanManageEventsFor`

For permission decisions against a target entity.

```php
interface CanManageEventsFor
{
    public function canManageEventsFor(
        mixed $manager,
        string $ability,
        mixed $target = null
    ): bool;
}
```

Abilities:

```text
create_events
edit_events
publish_events
archive_events
cancel_events
postpone_events
reschedule_events
approve_events
approve_submissions
manage_occurrences
manage_sessions
manage_locations
manage_speakers
manage_involvements
manage_materials
manage_links
manage_media
manage_registrations
manage_attendance
manage_tickets
manage_seating
manage_updates
send_notifications
```

Example: ilmu360 `Masjid` can decide whether a given AJK user may approve event submissions.

---

## 10. `RequiresEventApproval`

For entities or workflows that require approval.

```php
interface RequiresEventApproval
{
    public function eventApprovalRequiredFor(string $action): bool;

    public function eventApproversFor(string $action): iterable;
}
```

---

## 11. `AcceptsEventSubmissions`

For targets that can receive public proposed events.

```php
interface AcceptsEventSubmissions
{
    public function canAcceptEventSubmission(mixed $submitter): bool;

    public function defaultSubmissionStatus(): string;

    public function eventSubmissionApprovers(): iterable;
}
```

Example:

```text
Public user submits kuliah for Masjid Al-Falah.
Masjid AJK approves before publishing.
```

---

## 12. `CanBeInvolvedInEvents`

For speakers, organizers, sponsors, volunteers, teams, etc.

```php
interface CanBeInvolvedInEvents
{
    public function eventDisplayName(): string;

    public function eventDisplaySubtitle(): ?string;

    public function eventDisplayImage(): ?string;

    public function eventProfileUrl(): ?string;
}
```

---

## 13. `HasEventProminence`

For default importance of involvement roles.

```php
interface HasEventProminence
{
    public function defaultEventProminenceFor(string $roleCode): string;
}
```

Prominence:

```text
headliner
featured
supporting
operational
internal
```

This helps classify change impact. A headliner speaker change must create visible updates and notifications.

---

## 14. `CanBeEventMaterial`

For resources used in the event.

```php
interface CanBeEventMaterial
{
    public function eventMaterialTitle(): string;

    public function eventMaterialType(): string;

    public function eventMaterialUrl(): ?string;
}
```

Examples:

```text
Kitab
Book
File
SlideDeck
Module
Worksheet
Recording
```

---

## 15. `CanBeEventReference`

For cited/linked/supporting resources.

```php
interface CanBeEventReference
{
    public function eventReferenceTitle(): string;

    public function eventReferenceCitation(): ?string;

    public function eventReferenceUrl(): ?string;
}
```

---

## 16. `HasEventSchedule`

For models with event time.

```php
interface HasEventSchedule
{
    public function eventStartsAt(): ?DateTimeInterface;

    public function eventEndsAt(): ?DateTimeInterface;

    public function eventTimezone(): ?string;
}
```

---

## 17. `ResolvesEventTimeExpression`

For special time expressions such as prayer-time anchored events.

```php
interface ResolvesEventTimeExpression
{
    public function resolve(EventTimeExpression $expression, array $context = []): ?DateTimeInterface;
}
```

Generic package supports expression storage. Domain package provides resolver.

---

## 18. `HasEventLifecycle`

For lifecycle-safe event models.

```php
interface HasEventLifecycle
{
    public function publish(): void;

    public function cancel(?string $reason = null): void;

    public function postpone(?string $reason = null): void;

    public function delay(?string $reason = null, ?DateTimeInterface $expectedStartsAt = null): void;

    public function reschedule(DateTimeInterface $startsAt, DateTimeInterface $endsAt, array $options = []): void;

    public function complete(): void;

    public function archive(?string $reason = null): void;
}
```

Never let business code update status directly without lifecycle service.

---

## 19. `RecordsEventChanges`

For auditable changes.

```php
interface RecordsEventChanges
{
    public function recordEventChange(
        string $changeType,
        array $oldValue = [],
        array $newValue = [],
        array $context = []
    ): EventChangeLog;
}
```

---

## 20. `CanRegisterForEvents`

For registrant models.

```php
interface CanRegisterForEvents
{
    public function eventRegistrantName(): string;

    public function eventRegistrantEmail(): ?string;

    public function eventRegistrantPhone(): ?string;
}
```

---

## 21. `CanBeEventParticipant`

For participant models inside registrations.

```php
interface CanBeEventParticipant
{
    public function eventParticipantName(): string;

    public function eventParticipantEmail(): ?string;

    public function eventParticipantPhone(): ?string;
}
```

---

## 22. `Followable`

If generic interactions are included or integrated.

```php
interface Followable
{
    public function followers();

    public function followableName(): string;

    public function followableUrl(): ?string;
}
```

---

## 23. `Bookmarkable`

```php
interface Bookmarkable
{
    public function bookmarks();

    public function bookmarkTitle(): string;

    public function bookmarkUrl(): ?string;
}
```

---

# B. Traits

Traits should provide Eloquent relationships and small convenience methods only. Complex workflows belong to services.

Recommended traits:

```php
UsesEventUuid
HasEvents
HasEventOccurrences
HasEventSessions
HasEventAddress
ProvidesEventLocationSnapshot
HasEventLocations
HasEventSpaces
HasEventFacilities
CanOrganizeEvents
OwnsEvents
HasEventManagers
HasEventPermissions
HasEventInvolvements
UsedAsEventMaterial
ReferencedByEvents
HasEventLinks
HasEventMedia
HasEventLanguages
HasEventClassifications
HasEventAudience
HasEventEligibilityRules
HasEventRegistrations
HasEventParticipants
HasEventPasses
HasEventAttendances
HasEventLifecycleActions
RecordsEventChanges
PublishesEventUpdates
AcceptsEventSubmissions
ApprovesEventSubmissions
BelongsToEventSeries
FollowableTrait
BookmarkableTrait
HasEventResponses
```

## Trait rule

Do not put heavy business logic in traits. Traits should mostly define:

```text
relationships
scopes
small accessors
small helpers
```

Services should handle:

```text
approval
registration
pass issuance
seat allocation
check-in
change impact
notifications
submission conversion
rescheduling
```

---

# C. Service contracts

## Location services

### `EventLocationResolver`

Resolves a usable event location from venue, venue space, locationable model, or raw address.

```php
interface EventLocationResolver
{
    public function resolve(array $input): EventLocationData;
}
```

### `EventAddressSnapshotter`

Copies address/coordinates/maps from source into event location snapshot.

```php
interface EventAddressSnapshotter
{
    public function snapshot(HasEventAddress $source): array;
}
```

### `EventGeocoder`

```php
interface EventGeocoder
{
    public function geocode(EventLocation $location): GeocodingResult;
}
```

---

## Permission services

### `EventPermissionResolver`

```php
interface EventPermissionResolver
{
    public function can(mixed $user, string $ability, mixed $target): bool;
}
```

### `EventManagerResolver`

```php
interface EventManagerResolver
{
    public function managersFor(mixed $target, string $ability): iterable;
}
```

---

## Scheduling services

### `EventOccurrenceGenerator`

Creates occurrences for one-off, recurring, or series-based event schedules.

```php
interface EventOccurrenceGenerator
{
    public function generate(Event $event, array $rules): iterable;
}
```

### `EventTimeExpressionResolverRegistry`

```php
interface EventTimeExpressionResolverRegistry
{
    public function resolverFor(string $anchorType): ?ResolvesEventTimeExpression;
}
```

---

## Registration and access services

### `EventAccessPolicyEvaluator`

```php
interface EventAccessPolicyEvaluator
{
    public function evaluate(mixed $person, Event|EventOccurrence|EventSession $target): AccessDecision;
}
```

### `EventRegistrationService`

```php
interface EventRegistrationService
{
    public function register(array $data): EventRegistration;

    public function approve(EventRegistration $registration, mixed $actor = null): void;

    public function cancel(EventRegistration $registration, ?string $reason = null, mixed $actor = null): void;
}
```

### `EventPassIssuer`

```php
interface EventPassIssuer
{
    public function issuePassesFor(EventRegistration $registration): iterable;
}
```

### Seating allocation

Seat allocation is owned by `aiarmada/seating`. Use `AIArmada\Seating\Contracts\SeatAllocatorInterface` and `AIArmada\Seating\Models\SeatAllocation` for seat holds and allocations.

### `EventCheckInService`

```php
interface EventCheckInService
{
    public function checkIn(array $data): EventAttendance;

    public function checkOut(EventAttendance $attendance, mixed $actor = null): void;

    public function cancelCheckIn(EventAttendance $attendance, string $reason, mixed $actor = null): void;
}
```

---

## Change and notification services

### `EventChangeRecorder`

```php
interface EventChangeRecorder
{
    public function record(array $change): EventChangeLog;
}
```

### `EventChangeImpactClassifier`

```php
interface EventChangeImpactClassifier
{
    public function classify(EventChangeLog $change): ChangeImpact;
}
```

Rules:

```text
headliner speaker changed => critical
featured speaker changed => high
venue changed => high
time changed => high
topic changed => high
moderator changed => medium/high
person in charge changed => internal/medium
volunteer changed => internal/low
typo changed => low
```

### `EventUpdatePublisher`

```php
interface EventUpdatePublisher
{
    public function publishFromChange(EventChangeLog $change): ?EventUpdate;
}
```

### `EventNotificationRecipientResolver`

```php
interface EventNotificationRecipientResolver
{
    public function recipientsFor(EventUpdate $update, string $audienceScope): iterable;
}
```

### `EventNotificationDispatcher`

```php
interface EventNotificationDispatcher
{
    public function dispatch(EventNotificationBatch $batch): void;
}
```

---

## Submission and approval services

### `EventSubmissionConverter`

```php
interface EventSubmissionConverter
{
    public function convert(EventSubmission $submission): Event;
}
```

### `EventApprovalWorkflow`

```php
interface EventApprovalWorkflow
{
    public function requestApproval(mixed $approvable, mixed $target = null): EventApprovalRequest;

    public function approve(EventApprovalRequest $request, mixed $actor, ?string $notes = null): void;

    public function reject(EventApprovalRequest $request, mixed $actor, string $reason): void;
}
```

---

## Series services

### `DynamicEventSeriesResolver`

```php
interface DynamicEventSeriesResolver
{
    public function resolve(EventSeries $series): iterable;
}
```

---

# D. Package events/listeners

Use Laravel events to decouple side effects.

Recommended domain events:

```php
EventCreated
EventPublished
EventArchived
EventCancelled
EventOccurrenceCreated
EventOccurrenceDelayed
EventOccurrencePostponed
EventOccurrenceRescheduled
EventOccurrenceCancelled
EventSessionUpdated
EventLocationChanged
EventInvolvementChanged
EventSpeakerChanged
EventTopicChanged
EventRegistrationCreated
EventRegistrationApproved
EventRegistrationCancelled
EventPassIssued
EventAttendanceCheckedIn
EventAttendanceCheckedOut
EventUpdatePublished
EventNotificationBatchCreated
EventSubmissionReceived
EventSubmissionApproved
EventSubmissionRejected
```

Important rule:

State-changing services should emit events after successful transaction.

---

# E. Policies

Recommended policy abilities:

```text
viewAny
view
create
update
archive
publish
cancel
postpone
reschedule
manageOccurrences
manageSessions
manageLocations
manageInvolvements
manageMaterials
manageRegistrations
manageAttendance
manageTickets
manageSeating
manageUpdates
sendNotifications
approveSubmissions
manageManagers
```

Policies should delegate to `EventPermissionResolver` and domain contracts where appropriate.

---

# F. Checklist

- [x] Define all contracts in package namespace.
- [x] Provide traits for common Eloquent relationships.
- [x] Keep traits lightweight.
- [x] Implement services for workflows.
- [x] Ensure every direct state transition goes through a service.
- [x] Ensure lifecycle services write change logs where appropriate.
- [ ] Ensure important changes publish event updates. (event_updates created manually, not auto-chained from change_logs)
- [ ] Ensure important updates can create notification batches. (EventChangeNoticeWorkflow exists but auto-chaining from high-impact changes is not wired)
- [x] Keep domain logic out of generic package.
- [x] Allow domain packages to bind service implementations through Laravel container.
