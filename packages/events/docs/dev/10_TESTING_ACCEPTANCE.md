# 10 — Testing and Acceptance Criteria

This document defines quality gates and test scenarios.

## Global acceptance criteria

- [ ] All migrations run on a clean database. (no test suite yet)
- [ ] All migrations rollback cleanly. (no test suite yet)
- [x] No migration creates soft delete columns. (verified by lint)
- [x] No migration creates foreign key constraints. (verified by lint)
- [x] No migration creates cascading behavior. (verified by lint)
- [x] Every table has UUID primary key. (verified by lint)
- [x] Every normal mutable table has `created_at` and `updated_at` as timezone-aware timestamps. (verified by audit)
- [x] Lifecycle transitions use status + timestamp columns. (verified by audit)
- [x] All core workflows are service-driven. (verified by audit)
- [ ] Filament actions call services, not direct status edits. (Filament not implemented)

---

# Database tests

## Migration lint tests

Scan migration files and fail if they include forbidden patterns:

```text
softDeletes
softDeletesTz
foreign(
constrained(
cascadeOnDelete
restrictOnDelete
nullOnDelete
foreignId(
```

## Schema existence tests

- [x] Every table from `02_DATABASE_SCHEMA.md` exists. (65 migrations match the schema spec)
- [x] Every table has `id` UUID primary key. (verified by audit)
- [x] Every table that should have `metadata` has JSON/JSONB metadata. (verified by audit)
- [x] Every lifecycle timestamp column exists. (verified by audit)
- [x] No `deleted_at` exists. (verified by lint)

---

# Core event tests

## Event creation

- [ ] Create event in draft.
- [ ] Create event with owner polymorphic reference.
- [ ] Create event with occurrence.
- [ ] Create event with sessions.
- [ ] Event relationships load correctly.

## Event publish

- [ ] Publish event using service.
- [ ] `status = published`.
- [ ] `published_at` is set.
- [ ] Direct status update is not used in tests.

---

# Location tests

## Organizer-as-location

Create fake domain model `TestMasjid` implementing `HasEventAddress`.

- [ ] Use `TestMasjid` as event owner.
- [ ] Create event location from masjid address.
- [ ] Snapshot contains address.
- [ ] Snapshot contains coordinates.
- [ ] Snapshot contains Google Maps/Waze URL.
- [ ] Changing `TestMasjid` address later does not change old event location snapshot.

## Shared space type

- [ ] Create `venue_space_type = muslimah_hall`.
- [ ] Create event location using masjid + shared space type.
- [ ] No `venue_space` required.

## Coordinates

- [ ] Location can be filtered by state/district.
- [ ] Location can be queried by lat/lng range.
- [ ] If geo_point supported, radius query works.

---

# Involvement tests

## Public roles

- [ ] Add organizer involvement.
- [ ] Add speaker involvement.
- [ ] Add sponsor involvement.
- [ ] Add moderator to session.
- [ ] Add person in charge as internal visibility.

## Headliner change

- [ ] Replace headliner speaker.
- [ ] Change log is created.
- [ ] Impact level is high/critical.
- [ ] Public update is created.
- [ ] Notification batch can be created.

---

# Access and registration tests

## Individual registration

- [ ] Create individual registration.
- [ ] Participant row created.
- [ ] Answers saved.
- [ ] Pass issued.

## Family registration

- [ ] Create registration for 4 people.
- [ ] One registration header exists.
- [ ] Four participant rows exist.
- [ ] Passes issued according to ticket type.

## Group registration

- [ ] Create group registration with multiple participants.
- [ ] Registration items reflect selected ticket types.
- [ ] External order reference can be attached.
- [ ] Events package does not create payment/order records.

## Package ticket

- [ ] Family package admits 4.
- [ ] One registration item can issue 4 passes.

---

# Seating tests

## Reserved seating

- [ ] Create seat map.
- [ ] Create VIP section.
- [ ] Create seats.
- [ ] Hold seat.
- [ ] Convert hold to allocation.
- [ ] Allocate exact seat to pass.

## General/standing seating

- [ ] Create standing section.
- [ ] Allocate pass to section without seat.

## Expired hold

- [ ] Seat hold expires.
- [ ] Release command marks released_at.

---

# Attendance tests

## Registered check-in

- [ ] Check in with pass.
- [ ] Attendance row created.
- [ ] Attendance log created.
- [ ] Pass used_at set if single-use.

## Session check-in

- [ ] Check in to occurrence.
- [ ] Check in to session separately.

## Walk-in

- [ ] Free walk-in without registration creates attendance.
- [ ] Paid/formal walk-in can create registration first, then attendance.

---

# Content/discovery tests

## Materials vs references

- [ ] Add kitab/book as material.
- [ ] Add external citation as reference.
- [ ] Query materials does not return references.
- [ ] Query references does not return materials.

## Links

- [ ] Add live stream link.
- [ ] Add recording link.
- [ ] Link visibility works.
- [ ] Opens/expires windows work.

## Languages

- [ ] Add Malay delivery language.
- [ ] Add Arabic material language.
- [ ] Add English subtitle language.

## Eligibility

- [ ] Female-only rule blocks male test subject.
- [ ] Age range rule works.
- [ ] Muslim/non-Muslim informational rule can be displayed without blocking.

## Taxonomy

- [ ] Create taxonomy category.
- [ ] Create taxonomy knowledge_field.
- [ ] Classify event/session.
- [ ] Query by term works.

## Time expression

- [ ] Store selepas Subuh expression.
- [ ] Domain resolver resolves timestamp.
- [ ] Generic package does not hardcode prayer-time calculation.

---

# Change/update/notification tests

## Venue changed

- [ ] Old and new values stored.
- [ ] Public update created.
- [ ] Update item shows before/after.
- [ ] Notification batch can be created.

## Occurrence cancelled

- [ ] Status cancelled.
- [ ] cancelled_at set.
- [ ] Critical change log created.
- [ ] Pinned public update created.

## Occurrence postponed

- [ ] Status postponed.
- [ ] postponed_at set.
- [ ] Public update says new date not confirmed.

## Occurrence rescheduled

- [ ] Old occurrence links to new occurrence.
- [ ] New occurrence links to old occurrence.
- [ ] Before/after update created.

---

# Submission and approval tests

## Public submission

- [ ] Submit event for target model.
- [ ] Submission stores payload.
- [ ] Approval request created.
- [ ] Approver can approve.
- [ ] Converter creates real event records.
- [ ] Submission linked to created event.

## Management permission

- [ ] User with event management assignment can edit target event.
- [ ] User without assignment cannot edit.
- [ ] Domain model implementing `CanManageEventsFor` can grant permission.

---

# Interaction tests

## Follow

- [ ] User follows event.
- [ ] User follows speaker.
- [ ] User follows masjid-like test model.
- [ ] Unfollow sets `unfollowed_at` instead of deleting.

## Bookmark

- [ ] User bookmarks event.
- [ ] User bookmarks occurrence.
- [ ] User bookmarks speaker-like test model.
- [ ] Remove bookmark sets `removed_at`.

## Event response

- [ ] User marks interested.
- [ ] User marks going.
- [ ] Event response is not registration.
- [ ] Registration is not attendance.

---

# Filament acceptance tests

- [ ] EventResource can list/create/view/edit events.
- [ ] OccurrenceResource lifecycle actions use service.
- [ ] SessionResource supports relation managers.
- [ ] Check-in console can check in by pass number/QR.
- [ ] Approval queue can approve/reject submission.
- [ ] Notification center can create/send batch.
- [ ] Seat map page can manage sections/seats.
- [ ] Public preview shows active pinned updates.
- [ ] Policies are enforced.

---

# Example integration scenario: ilmu360-like domain

Create test domain models:

```text
TestMasjid implements HasEventAddress, CanOrganizeEvents, CanManageEventsFor, AcceptsEventSubmissions
TestSpeaker implements CanBeInvolvedInEvents
TestKitab implements CanBeEventMaterial, CanBeEventReference
TestPrayerTimeResolver implements ResolvesEventTimeExpression
```

Scenario:

1. A public user submits Kuliah Maghrib for TestMasjid.
2. AJK-like user approves.
3. Event is created with occurrence.
4. Masjid is organizer and venue source.
5. Speaker is attached as headliner.
6. Kitab is attached as main material.
7. Time expression `Selepas Maghrib` resolves.
8. Users follow speaker and masjid.
9. Speaker changes.
10. Public update and notification batch are created.
11. Walk-in attendance is checked in.

Acceptance:

- [ ] All steps pass without domain-specific columns in generic event tables.
- [ ] All integrations use contracts/polymorphic references.

---

# Production readiness checklist

- [x] Migrations are safe. (config-driven, no FK/cascade/soft deletes)
- [x] No FK/cascade/soft delete. (verified by lint)
- [ ] Services are transactional where needed. (not verified)
- [ ] Critical workflows are tested. (no tests yet)
- [ ] Filament actions do not bypass services. (Filament not implemented)
- [ ] Change logs and updates are produced for material changes. (change_logs produced; auto-chain to event_updates not wired)
- [x] Notifications are trackable. (EventNotificationBatch + EventNotificationDelivery models/workflow exist)
- [ ] Domain integration example exists. (no test domain models yet)
- [ ] Documentation is complete. (dev docs exist; user docs not yet written)
