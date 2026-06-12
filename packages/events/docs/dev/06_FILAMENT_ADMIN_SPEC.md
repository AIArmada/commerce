# 06 — Filament Administration Specification

This document defines the Filament administration counterpart for the generic Events package.

The Filament admin must be powerful enough for real event operations, not just CRUD. It should support planning, publishing, registrations, check-in, changes, notifications, submissions, approvals, seating, locations, and content.

## Filament principles

- Build CRUD resources for every important model.
- Use Relation Managers heavily for event-scoped child records.
- Use custom pages for operational workflows: calendar, check-in, seating map, approval queue, notification center.
- Use widgets for event health and operations summary.
- Use actions for lifecycle transitions instead of letting admins manually edit status fields.
- Hide dangerous direct edits where service workflow is required.
- Keep payment/order details read-only references because commerce is external.
- Use navigation groups to avoid an unusable mega sidebar.

---

# Navigation groups

Recommended groups:

```text
Events
Scheduling
Locations
People & Involvement
Registrations & Access
Attendance
Content & Media
Audience & Discovery
Changes & Notifications
Submissions & Approval
Interactions
Configuration
Reports
```

---

# Core resources

## 1. `EventResource`

Primary admin resource.

### Pages

```text
ListEvents
CreateEvent
ViewEvent
EditEvent
EventDashboard
EventCalendar
EventTimeline
EventOperations
```

### Form sections

```text
Identity
- title
- slug
- summary
- description
- type

Ownership
- owner_type
- owner_id
- created_by display

Lifecycle
- status display only
- visibility
- delivery_mode
- timezone
- published_at display
- cancelled_at display
- postponed_at display
- archived_at display

Default venue
- default_venue_id optional

Metadata
- metadata key/value editor or JSON editor
```

### Table columns

```text
title
status badge
visibility badge
delivery_mode badge
next occurrence
owner
public organizer
published_at
updated_at
```

### Filters

```text
status
visibility
delivery_mode
type
owner
has upcoming occurrence
has registrations
has urgent updates
created date
published date
```

### Header actions

```text
Publish
Archive
Cancel
Postpone
Create Occurrence
Create Session
Create Public Update
Send Notification
Open Public URL
Duplicate Event
```

### Relation managers

```text
OccurrencesRelationManager
SessionsRelationManager
LocationsRelationManager
FacilitiesRelationManager
InvolvementsRelationManager
TicketTypesRelationManager
RegistrationsRelationManager
PassesRelationManager
AttendancesRelationManager
MaterialsRelationManager
ReferencesRelationManager
LinksRelationManager
MediaRelationManager
LanguagesRelationManager
AudiencesRelationManager
EligibilityRulesRelationManager
ClassificationsRelationManager
TimeExpressionsRelationManager
ItinerariesRelationManager
SeriesItemsRelationManager
UpdatesRelationManager
ChangeLogsRelationManager
NotificationBatchesRelationManager
ManagementAssignmentsRelationManager
SubmissionsRelationManager
```

### Widgets on View/Edit

```text
EventStatusOverviewWidget
NextOccurrencesWidget
RegistrationStatsWidget
AttendanceStatsWidget
TicketQuotaWidget
ImportantUpdatesWidget
LineupWidget
LocationSummaryWidget
```

---

## 2. `EventOccurrenceResource`

Operational resource for dates/instances.

### Pages

```text
ListOccurrences
CreateOccurrence
ViewOccurrence
EditOccurrence
OccurrenceDashboard
OccurrenceCheckIn
OccurrenceSeating
OccurrenceTimeline
```

### Form sections

```text
Parent event
Date/time
- starts_at
- ends_at
- timezone

Status & visibility
Delivery mode
Capacity
Lifecycle timestamps display
Reschedule links display
Status reason/message
Metadata
```

### Actions

```text
Publish
Delay
Postpone
Reschedule
Cancel
Complete
Create Session
Create Registration
Open Check-in
Send Update
Duplicate Occurrence
```

### Relation managers

```text
Sessions
Locations
Facilities
Involvements
TicketTypes
Registrations
Passes
Attendances
SeatMaps
Materials
References
Links
Media
Languages
AudienceProfiles
EligibilityRules
Classifications
TimeExpressions
Updates
ChangeLogs
NotificationBatches
Itineraries
```

---

## 3. `EventSessionResource`

Agenda item management.

### Pages

```text
ListSessions
CreateSession
ViewSession
EditSession
SessionTimeline
```

### Key features

- Drag sort within occurrence.
- Manage session speaker/panel/moderator line-up.
- Manage session materials and references.
- Manage session location.
- Manage session attendance if enabled.

---

# Location resources

## 4. `VenueResource`

Manage reusable venues.

### Relation managers

```text
ChildVenues
VenueSpaces
VenueFacilities
EventLocations
```

### Actions

```text
Geocode
Open Google Maps
Open Waze
Create Child Venue
Create Space
```

## 5. `VenueSpaceResource`

Manage persisted venue spaces.

## 6. `VenueSpaceTypeResource`

Manage shared reusable location labels.

## 7. `EventLocationResource`

Manage event-specific locations.

### Key features

- Choose locationable source if supported.
- Snapshot address.
- Set Google Maps/Waze URLs.
- Set coordinates.
- Set role: primary, parking, registration counter, session room, online, etc.

## 8. `FacilityTypeResource`

Facility catalog.

## 9. `EventFacilityResource`

Event/occurrence/session facility display.

---

# Involvement resources

## 10. `EventRoleResource`

Manage public event role codes.

## 11. `EventInvolvementResource`

Manage speakers, organizers, sponsors, partners, moderators, etc.

### Form features

```text
Scope: event / occurrence / session
Involveable model selector
Role
Status
Visibility
Prominence
Featured/primary flags
Replacement info
Notes
```

### Actions

```text
Mark Featured
Mark Headliner
Replace Involvement
Remove Publicly
Create Change Update
```

### Important behavior

Replacing a headliner/featured speaker must create change log and suggest public update/notification.

---

# Registration and access resources

## 12. `EventAccessPolicyResource`

Manage access rules.

## 13. `EventRegistrationResource`

Registration header management.

### Pages

```text
ListRegistrations
CreateRegistration
ViewRegistration
EditRegistration
RegistrationParticipants
RegistrationPasses
```

### Columns

```text
registration_no
event
occurrence
registrant
type
status
source
total_participants
payment_status
registered_at
approved_at
```

### Actions

```text
Approve
Reject
Cancel
Waitlist
Issue Passes
Create Attendance
Open External Order
```

### Relation managers

```text
Participants
Answers
Items
Passes
Attendances
```

## 14. `EventRegistrationParticipantResource`

Can be mainly relation-managed under registration.

## 15. `EventTicketTypeResource`

Ticket/access definitions.

### Features

- Define entry/seating/standing/package/addon.
- Define seating mode.
- Define quota and sales window.
- Define admits quantity.
- Define component ticket types for packages.
- Define seating options.

## 16. `EventPassResource`

Issued passes.

### Actions

```text
Activate
Cancel
Revoke
Void
Mark Used
Regenerate QR
Allocate Seat
Check In
```

---

# Seating resources

## 17. `EventSeatMapResource`

Manage seat maps.

### Pages

```text
ListSeatMaps
CreateSeatMap
ViewSeatMap
EditSeatMap
SeatMapDesigner
```

## 18. `EventSeatSectionResource`

Manage sections/areas.

## 19. `EventSeatResource`

Manage individual seats.

## 20. `EventSeatHoldResource`

Operational view for active/expired holds.

## 21. `EventSeatAllocationResource`

Final allocations.

### Widgets

```text
SeatAvailabilityWidget
SeatAllocationStatsWidget
```

---

# Attendance resources

## 22. `EventAttendanceResource`

Attendance truth.

### Pages

```text
ListAttendances
CreateAttendance
ViewAttendance
EditAttendance
CheckInConsole
```

### Actions

```text
Check In
Check Out
Correct
Cancel Check-in
Mark Absent
```

## 23. `EventAttendanceLogResource`

Read-only audit resource.

---

# Content resources

## 24. `EventMaterialResource`

Manage used/delivered resources.

## 25. `EventReferenceResource`

Manage cited/linked/supporting references.

## 26. `EventLinkResource`

Manage event URLs.

Important link types:

```text
official website
registration
live stream
recording
online meeting
Google Maps
Waze
feedback
certificate
```

## 27. `EventMediaResource`

Manage posters, covers, galleries, videos, recordings.

## 28. `EventLanguageResource`

Manage delivery/material/subtitle/translation languages.

---

# Audience and discovery resources

## 29. `EventAudienceResource`

Marketing audience labels.

## 30. `EventAudienceProfileResource`

Age range and child-friendly flags.

## 31. `EventEligibilityRuleResource`

Restriction rules.

## 32. `EventTaxonomyResource`

Taxonomy groups.

## 33. `EventTermResource`

Taxonomy terms.

## 34. `EventClassificationResource`

Term assignments.

## 35. `EventTimeExpressionResource`

Special times.

### Actions

```text
Resolve Time
Preview Resolution
Clear Resolution
```

---

# Itinerary and series resources

## 36. `EventItineraryResource`

Manage public/VIP/speaker/staff/group/package itineraries.

## 37. `EventItineraryItemResource`

Relation-managed under itinerary.

## 38. `EventSeriesResource`

Manage curated/dynamic series.

## 39. `EventSeriesItemResource`

Relation-managed under series.

## 40. `EventSeriesRuleResource`

Optional dynamic rules.

---

# Change, update, notification resources

## 41. `EventChangeLogResource`

Read-only or limited admin resource.

Must show:

```text
change_type
change_category
impact_level
requires_notification
old_value
new_value
changed_by
changed_at
```

## 42. `EventUpdateResource`

Public update management.

### Actions

```text
Publish Update
Pin/Unpin
Archive
Create Notification Batch
```

## 43. `EventUpdateItemResource`

Relation-managed under update.

## 44. `EventNotificationBatchResource`

Notification campaigns.

### Actions

```text
Preview Recipients
Send Now
Cancel Scheduled
Retry Failed
```

## 45. `EventNotificationDeliveryResource`

Delivery tracking.

---

# Submissions and approval resources

## 46. `EventSubmissionResource`

Public submission moderation.

### Pages

```text
ListSubmissions
ViewSubmission
ReviewSubmission
```

### Actions

```text
Start Review
Approve
Reject
Request Changes
Convert to Event
```

## 47. `EventApprovalRequestResource`

Approval queue.

### Actions

```text
Approve
Reject
Assign to Me
Reassign
Cancel Request
```

## 48. `EventManagementAssignmentResource`

Manage owners/editors/approvers/partners.

---

# Interaction resources

If interactions included in package:

```text
FollowResource
BookmarkResource
BookmarkCollectionResource
EventResponseResource
EventReminderResource
ReactionResource
InteractionEventResource
```

Interaction events should generally be read-only analytics.

---

# Dashboard widgets

## Global dashboard widgets

```text
UpcomingEventsStatsWidget
PublishedVsDraftEventsWidget
RegistrationTrendWidget
AttendanceTrendWidget
PendingApprovalsWidget
UrgentUpdatesWidget
CancelledPostponedEventsWidget
TopFollowedEventsWidget
TopSpeakersWidget
NearbyEventsWidget
```

## Event-specific widgets

```text
EventHealthWidget
EventTimelineWidget
OccurrenceCalendarWidget
RegistrationFunnelWidget
AttendanceFunnelWidget
TicketQuotaWidget
SeatAvailabilityWidget
InvolvementLineupWidget
ImportantUpdatesWidget
NotificationDeliveryWidget
LocationMapWidget
```

---

# Custom pages

## `EventCalendarPage`

- Calendar view of occurrences.
- Filter by status, venue, owner, category, speaker, delivery mode.
- Drag/drop rescheduling must call lifecycle service and create change logs.

## `CheckInConsolePage`

- Search by registration number, pass number, QR, name, phone.
- Support walk-in creation.
- Show access decision clearly.
- Check-in must use `EventCheckInService`.

## `ApprovalQueuePage`

- Show event submissions and approval requests.
- Filter by target owner/masjid/organization.
- Bulk assign/review.

## `NotificationCenterPage`

- Create notification batches from updates.
- Preview recipients.
- Send/retry deliveries.

## `SeatMapDesignerPage`

- Create sections and seats.
- Import seats.
- View holds and allocations.

## `EventPublicPreviewPage`

- Preview public event page data before publishing.
- Show active updates, locations, speakers, tickets, facilities, materials.

---

# Form UX rules

- Lifecycle fields are mostly read-only; use actions.
- Public impact changes must show warning modals.
- Replacing headliner/featured speaker must prompt to create public update.
- Venue/time changes must prompt to notify registrants.
- Cancellation/postponement actions must require reason.
- All online links must support visibility and opens/expires window.
- All location forms should include coordinates and Google Maps/Waze URL.
- All polymorphic selectors should be searchable and configurable by host app.

---

## Checklist

- [x] Create resources for all core models. (done in packages/filament-events)
- [x] Add relation managers under Event, Occurrence, Session. (7 relation managers under EventResource)
- [x] Add lifecycle actions that call services. (Publish/Archive/Cancel on EventResource, Delay/Postpone/Cancel/Complete on OccurrenceResource)
- [x] Add widgets for operational visibility. (EventStatsWidget done)
- [x] Add check-in console. (CheckInConsole page with pass/QR search + walk-in)
- [x] Add approval queue. (ApprovalQueue page with approve/reject/assign)
- [x] Add notification center. (NotificationCenter page with send/cancel)
- [ ] Add seat map management. (not done)
- [x] Add public preview. (EventPublicPreview page showing all event details)
- [ ] Keep audit logs read-only unless correction is explicitly supported. (not done)
- [ ] Use policies for every resource action. (not done)
- [x] Avoid direct status edits where workflow action exists. (lifecycle actions call EventLifecycleWorkflow)
