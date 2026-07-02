# 03 — Models and Relationships

This document defines the expected Eloquent models and relationship semantics.

The package must not rely on database foreign key constraints. Relationships are still defined in Eloquent using `hasMany`, `belongsTo`, `morphTo`, and `morphMany`, but integrity is enforced through services, validators, policies, and tests.

## Global model rules

Every model should:

- Use UUID primary keys.
- Use string primary key config.
- Avoid soft deletes.
- Cast timestamp fields to immutable datetime where appropriate.
- Cast JSONB columns to array or value objects.
- Expose status/visibility/type values through constants or backed enums at code level.
- Avoid direct destructive deletes in business workflows. Prefer status transitions such as archived, cancelled, revoked, voided, expired, removed.

Suggested base trait:

```php
trait UsesEventUuid
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected static function bootUsesEventUuid(): void
    {
        static::creating(function ($model) {
            if (! $model->getKey()) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
```

## Relationship design rule

Even though database constraints are not used, every relationship must still be clearly expressed in the model layer.

Example:

```php
class EventOccurrence extends Model
{
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
```

The application must validate that referenced records exist before writing.

---

# Core event models

## `Event`

Represents event identity/program.

Relationships:

```text
hasMany EventOccurrence
hasMany EventSession
hasMany EventLocation
hasMany EventFacility
hasMany EventInvolvement
hasMany EventAccessPolicy
hasMany EventRegistration
hasMany EventTicketType
hasMany EventPass
hasMany EventAttendance
hasMany EventMaterial
hasMany EventReference
hasMany EventLink
hasMany EventMedia
hasMany EventLanguage
hasMany EventAudience
hasMany EventAudienceProfile
hasMany EventEligibilityRule
hasMany EventClassification
hasMany EventTimeExpression
hasMany EventItinerary
hasMany EventChangeLog
hasMany EventUpdate
hasMany EventNotificationBatch
morphTo owner
morphTo createdBy
```

Important methods:

```php
$event->publish();
$event->cancel($reason);
$event->archive($reason);
$event->primaryLocation();
$event->publicUpdates();
$event->featuredInvolvements();
$event->headliners();
$event->nextOccurrence();
$event->isPubliclyVisible();
```

## `EventOccurrence`

Actual scheduled happening.

Relationships:

```text
belongsTo Event
hasMany EventSession
hasMany EventLocation
hasMany EventFacility
hasMany EventInvolvement
hasMany EventAccessPolicy
hasMany EventRegistration
hasMany EventTicketType
hasMany EventPass
hasMany EventAttendance
hasMany EventMaterial
hasMany EventReference
hasMany EventLink
hasMany EventMedia
hasMany EventLanguage
hasMany EventAudience
hasMany EventAudienceProfile
hasMany EventEligibilityRule
hasMany EventClassification
hasMany EventTimeExpression
hasMany EventItinerary
hasMany EventChangeLog
hasMany EventUpdate
hasMany EventNotificationBatch
belongsTo rescheduledFromOccurrence
belongsTo rescheduledToOccurrence
```

Important methods:

```php
$occurrence->delay($reason, $expectedStartsAt = null);
$occurrence->postpone($reason);
$occurrence->reschedule($newStartsAt, $newEndsAt, $strategy = 'linked');
$occurrence->cancel($reason);
$occurrence->complete();
$occurrence->primaryLocation();
$occurrence->registrationPolicy();
$occurrence->availableTicketTypes();
$occurrence->activeUpdates();
```

## `EventSession`

Agenda item inside occurrence.

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence
hasMany scoped EventLocation
hasMany scoped EventFacility
hasMany scoped EventInvolvement
hasMany scoped EventMaterial
hasMany scoped EventReference
hasMany scoped EventLink
hasMany scoped EventMedia
hasMany scoped EventLanguage
hasMany scoped EventAudience
hasMany scoped EventEligibilityRule
hasMany scoped EventAttendance
hasMany scoped EventChangeLog
hasMany scoped EventUpdate
```

Important methods:

```php
$session->speakerLineup();
$session->materials();
$session->references();
$session->isLiveNow();
$session->activeUpdates();
```

---

# Location models

## `Venue`

Reusable real location.

Relationships:

```text
belongsTo parentVenue
hasMany childVenues
hasMany VenueSpace
hasMany VenueFacility
morphMany EventLocation as locationable
```

Capabilities:

```text
HasEventAddress
HasEventCoordinates
HasEventMapLinks
CanBeGeocodedForEvents
ProvidesEventFacilities
```

## `VenueSpace`

Concrete persisted space inside a venue.

Relationships:

```text
belongsTo Venue
hasMany VenueFacility
hasMany EventLocation
```

## `VenueSpaceType`

Reusable shared sublocation label.

Relationships:

```text
hasMany EventLocation
```

## `EventLocation`

Actual location assignment.

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
morphTo locationable nullable
belongsTo Venue nullable
belongsTo VenueSpace nullable
belongsTo VenueSpaceType nullable
hasMany EventFacility
```

Important methods:

```php
$location->isEventLevel();
$location->isOccurrenceLevel();
$location->isSessionLevel();
$location->navigationUrl();
$location->coordinates();
$location->displayAddress();
```

## `FacilityType`

Facility catalog.

Relationships:

```text
hasMany VenueFacility
hasMany EventFacility
```

## `VenueFacility`

Verified permanent facility.

Relationships:

```text
belongsTo Venue
belongsTo VenueSpace nullable
belongsTo FacilityType
```

## `EventFacility`

Event-specific facility.

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
belongsTo EventLocation nullable
belongsTo FacilityType
```

---

# Involvement models

## `EventRole`

Public event role code.

Relationships:

```text
hasMany EventInvolvement
```

## `EventInvolvement`

Public relationship between event and involved entity.

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
belongsTo EventRole nullable
morphTo involveable
belongsTo replacedByInvolvement nullable
```

Important scopes:

```php
scopeRole($query, string $roleCode)
scopePublic($query)
scopeFeatured($query)
scopeHeadliner($query)
scopeForEventLevel($query)
scopeForOccurrence($query, $occurrenceId)
scopeForSession($query, $sessionId)
```

Important methods:

```php
$involvement->isHeadliner();
$involvement->isPublic();
$involvement->shouldTriggerImportantChange();
$involvement->displayName();
```

---

# Registration and access models

## `EventAccessPolicy`

Defines access/registration requirements.

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
```

Important methods:

```php
$policy->requiresRegistration();
$policy->requiresTicket();
$policy->allowsWalkIn();
$policy->requiresSeating();
$policy->isOpenForRegistration();
```

## `EventRegistration`

Registration header.

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
morphTo registrant nullable
hasMany EventRegistrationParticipant
hasMany EventRegistrationAnswer
hasMany EventRegistrationItem
hasMany EventPass
hasMany EventAttendance
morphTo externalOrder nullable
```

Important methods:

```php
$registration->approve();
$registration->reject($reason);
$registration->cancel($reason);
$registration->waitlist();
$registration->issuePasses();
$registration->isPaidExternally();
$registration->participantsCount();
```

## `EventRegistrationParticipant`

Person covered by registration.

Relationships:

```text
belongsTo EventRegistration
morphTo participant nullable
hasMany EventRegistrationAnswer
hasMany EventPass
hasMany EventAttendance
```

## `EventRegistrationAnswer`

Registration or participant answer.

Relationships:

```text
belongsTo EventRegistration
belongsTo EventRegistrationParticipant nullable
```

## `EventRegistrationItem`

Ticket/package line selected under registration.

Relationships:

```text
belongsTo EventRegistration
belongsTo EventTicketType
hasMany EventPass
morphTo externalOrderItem nullable
```

## `EventTicketType`

Access definition.

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
hasMany EventRegistrationItem
hasMany EventPass
hasMany EventTicketTypeComponent as parent
hasMany EventTicketTypeSeatingOption
```

Important methods:

```php
$ticketType->isPackage();
$ticketType->isReservedSeat();
$ticketType->isStanding();
$ticketType->admitsQuantity();
$ticketType->availableQuota();
```

## `EventTicketTypeComponent`

Package composition.

Relationships:

```text
belongsTo parentTicketType
belongsTo componentTicketType
```

## `EventPass`

Issued access.

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
belongsTo EventRegistration nullable
belongsTo EventRegistrationParticipant nullable
belongsTo EventRegistrationItem nullable
belongsTo EventTicketType nullable
hasMany EventAttendance
```

Important methods:

```php
$pass->activate();
$pass->cancel($reason);
$pass->revoke($reason);
$pass->void($reason);
$pass->markUsed();
$pass->isValidFor($target);
```

---

# Seating models

Seating models moved to `aiarmada/seating`.

Events links to seating through polymorphic `SeatMap::seatable`; `Event`, `EventOccurrence`, and `EventSession` expose `morphMany(SeatMap::class, 'seatable')`. Final seat assignments are `AIArmada\Seating\Models\SeatAllocation` records whose `allocated_to` morph can point at an event pass.

---

# Attendance models

## `EventAttendance`

Actual attendance/check-in.

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence
belongsTo EventSession nullable
belongsTo EventRegistration nullable
belongsTo EventRegistrationParticipant nullable
belongsTo EventPass nullable
morphTo attendee nullable
hasMany EventAttendanceLog
```

Important methods:

```php
$attendance->checkIn();
$attendance->checkOut();
$attendance->correct($data);
$attendance->cancelCheckIn($reason);
```

## `EventAttendanceLog`

Relationships:

```text
belongsTo EventAttendance
morphTo performedBy nullable
```

---

# Materials, references, media, links, languages

## `EventMaterial`

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
morphTo material nullable
```

## `EventReference`

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
morphTo referenceable nullable
```

## `EventLink`

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
```

## `EventMedia`

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
```

## `EventLanguage`

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
```

---

# Audience, eligibility, taxonomy, time

## `EventAudience`

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
```

## `EventAudienceProfile`

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
```

## `EventEligibilityRule`

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
```

Important method:

```php
$rule->evaluate($personOrContext): EligibilityResult;
```

## `EventTaxonomy`

Relationships:

```text
hasMany EventTerm
hasMany EventClassification
```

## `EventTerm`

Relationships:

```text
belongsTo EventTaxonomy
belongsTo parent EventTerm nullable
hasMany children EventTerm
hasMany EventClassification
```

## `EventClassification`

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
belongsTo EventTaxonomy
belongsTo EventTerm
```

## `EventTimeExpression`

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
```

Important method:

```php
$expression->resolveUsing(EventTimeExpressionResolver $resolver);
```

---

# Itinerary and series models

## `EventItinerary`

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
morphTo owner nullable
hasMany EventItineraryItem
```

## `EventItineraryItem`

Relationships:

```text
belongsTo EventItinerary
belongsTo EventSession nullable
belongsTo Venue nullable
belongsTo EventLocation nullable
```

## `EventSeries`

Relationships:

```text
morphTo owner nullable
hasMany EventSeriesItem
hasMany EventSeriesRule
```

## `EventSeriesItem`

Relationships:

```text
belongsTo EventSeries
morphTo seriesable
belongsTo Event nullable
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
```

## `EventSeriesRule`

Relationships:

```text
belongsTo EventSeries
```

---

# Change, update, notification models

## `EventChangeLog`

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
morphTo changedBy nullable
morphTo subject nullable
hasOne EventUpdate
```

## `EventUpdate`

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
belongsTo EventChangeLog nullable
morphTo createdBy nullable
hasMany EventUpdateItem
hasMany EventNotificationBatch
```

## `EventUpdateItem`

Relationships:

```text
belongsTo EventUpdate
```

## `EventNotificationBatch`

Relationships:

```text
belongsTo Event
belongsTo EventOccurrence nullable
belongsTo EventSession nullable
belongsTo EventUpdate nullable
belongsTo EventChangeLog nullable
morphTo createdBy nullable
hasMany EventNotificationDelivery
```

## `EventNotificationDelivery`

Relationships:

```text
belongsTo EventNotificationBatch
morphTo recipient
```

# Submission and approval models

## `EventSubmission`

Relationships:

```text
morphTo submitter nullable
morphTo target nullable
belongsTo Event nullable
belongsTo EventOccurrence nullable
hasMany EventSubmissionLog
hasMany EventSubmissionAttachment
hasMany EventApprovalRequest as approvable
```

## `EventSubmissionLog`

Relationships:

```text
belongsTo EventSubmission
morphTo performedBy nullable
```

## `EventSubmissionAttachment`

Relationships:

```text
belongsTo EventSubmission
```

## `EventApprovalRequest`

Relationships:

```text
morphTo approvable
morphTo target nullable
morphTo requestedBy nullable
morphTo assignedTo nullable
```

## `EventManagementAssignment`

Relationships:

```text
morphTo manageable
morphTo manager
morphTo assignedBy nullable
```

---

# Important read model rules

Read models are allowed for performance, but must not become business truth.

Possible read models/projections:

```text
event_search_index
event_public_cards
event_occurrence_calendar_index
event_nearby_index
event_speaker_index
event_topic_index
event_participant_view
```

These should be rebuilt from source tables.

Do not replace core source tables with read models.

## Relationship checklist

- [x] Every model defines UUID key config.
- [x] Every reference column has an Eloquent relationship where applicable.
- [x] Every polymorphic relationship has matching `morphTo` / `morphMany` usage.
- [x] No model uses SoftDeletes.
- [x] No business workflow uses direct `delete()` without explicit approval.
- [x] Event/occurrence/session scoped models expose helper scopes.
- [x] Public event role and internal management permission are separate.
- [x] Registration, response, and attendance are not confused.
- [x] Pass issuance is separate from ticket type definition.
- [x] Seat allocation is separate from ticket type and pass.
