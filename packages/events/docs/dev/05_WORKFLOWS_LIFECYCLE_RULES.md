# 05 — Workflows and Lifecycle Rules

This document explains how the package should be used correctly. Tables alone are not enough; workflows must protect event integrity.

---

# 1. Event creation workflow

## Create basic event

1. Validate user can create event for owner/target.
2. Create `events` row with `status = draft` or `pending_review`.
3. If organizer is known, create `event_involvements` with `role_code = organizer`.
4. If owner has address and should be venue, create `event_locations` from `HasEventAddress` snapshot.
5. Create default access policy if needed.
6. Create occurrences.
7. Create sessions if agenda is supplied.
8. Create materials/references/links/media/languages/classifications as supplied.
9. If approval required, create `event_approval_requests`.
10. If no approval required and publish requested, call `publish()` service.

## Do not

- Do not directly set `published_at` without lifecycle service.
- Do not assume organizer is manager.
- Do not assume owner is public organizer.
- Do not skip location snapshot when using locationable address.

---

# 2. Publishing workflow

1. Validate required data:
   - title
   - visibility
   - at least one occurrence or valid event-level schedule
   - location or online link depending on delivery mode
   - access policy if registration/ticketing is enabled
2. Validate user permission.
3. Set:

```text
status = published
published_at = now()
```

4. Create `event_change_logs` with `change_type = published`.
5. Optionally create `event_updates` if public announcement is needed.
6. Dispatch `EventPublished`.

---

# 3. Occurrence cancellation workflow

1. Validate permission.
2. Store old occurrence state.
3. Set:

```text
status = cancelled
cancelled_at = now()
status_reason = reason
```

4. Create `event_change_logs`:

```text
change_type = cancelled
change_category = status
impact_level = critical
requires_notification = true
```

5. Create pinned `event_updates`:

```text
update_type = cancellation
severity = critical
is_pinned = true
```

6. Create notification batch for affected audience:

```text
audience_scope = registrants
channels = configured channels
```

7. Dispatch `EventOccurrenceCancelled`.

---

# 4. Postponement workflow

Use postponed when new date/time is not confirmed.

1. Set:

```text
status = postponed
postponed_at = now()
status_reason = reason
```

2. Create critical/high change log.
3. Create pinned update.
4. Notify registrants/followers/interested users.

Public message should be clear:

```text
This occurrence has been postponed. New date will be announced later.
```

---

# 5. Reschedule workflow

Use rescheduled when new date/time is known.

## Preferred serious strategy: linked occurrence

1. Mark old occurrence:

```text
status = rescheduled
rescheduled_at = now()
rescheduled_to_occurrence_id = new_occurrence_id
```

2. Create new occurrence:

```text
rescheduled_from_occurrence_id = old_occurrence_id
starts_at = new date/time
ends_at = new end time
status = scheduled/published
```

3. Move or copy registrations according to configured policy.
4. Create change log with old/new dates.
5. Create public update with before/after.
6. Notify affected people.

## Simple strategy

Update same occurrence `starts_at` / `ends_at` and record change log. Use only when historical original occurrence identity is not important.

---

# 6. Delay workflow

Use delay when event is still happening, usually same day, but later.

1. Set:

```text
status = delayed
delayed_at = now()
```

2. Put expected new start in metadata or event_time_expressions if needed.
3. Create urgent update if attendees are affected.
4. Notify registrants/attendees if event is near.

---

# 7. Venue/location change workflow

1. Validate permission.
2. Store old event location snapshot.
3. Create or update `event_locations`.
4. If using `HasEventAddress`, snapshot source address/coordinates/map links.
5. Create `event_change_logs`:

```text
change_type = venue_changed
change_category = venue
impact_level = high
requires_notification = true
```

6. Create public update:

```text
Venue changed
Before: old venue/address
Now: new venue/address
```

7. Notify registrants/interested users.

---

# 8. Speaker/moderator/panel/PIC change workflow

All public line-up roles are stored as `event_involvements`.

1. Determine role and prominence.
2. Store old involvement(s).
3. Create/update/replace involvement records.
4. If replacing, set `replaced_by_involvement_id` and `replacement_reason` where useful.
5. Create `event_change_logs`.
6. Classify impact:

```text
headliner speaker changed => critical
featured speaker changed => high
panelist changed => high/medium
moderator changed => medium/high
person in charge changed and internal => medium/internal
volunteer changed => low/internal
```

7. Create public update only if visible/public impact.
8. Notify audience based on impact.

Important: for talks where people attend because of speaker first, headliner/featured speaker changes must be clearly pinned.

---

# 9. Topic/title/category change workflow

1. Update event/session title/classification.
2. Create change log with before/after.
3. If topic is primary selling reason, impact is high.
4. Create public update:

```text
Topic updated
Before: old topic
Now: new topic
```

5. Notify registrants/followers if high impact.

---

# 10. Online link / live link / recording workflow

Use `event_links`.

## Live link added

1. Create `event_links`:

```text
link_type = live_stream or online_meeting
visibility = public/private/registered_only
opens_at = optional
```

2. If close to event time, create event update.
3. Notify registrants if useful.

## Recording available

1. Create `event_links` or `event_media` depending on file/link.
2. Create update:

```text
update_type = recording_available
severity = info/important
```

3. Notify attendees/registrants based on configuration.

---

# 11. Registration workflow

## Individual registration

1. Validate access policy.
2. Validate eligibility rules.
3. Validate capacity/quota.
4. Create `event_registrations`.
5. Create primary `event_registration_participants`.
6. Create answers if form supplied.
7. Create registration items if ticket/access selected.
8. If payment required, attach external order reference but do not process payment here.
9. If approval required, set status pending.
10. If no approval/payment blocking, approve/confirm.
11. Issue passes when allowed.

## Family/friend/group registration

1. Create one registration header.
2. Create one participant row per person.
3. Create participant-level answers where needed.
4. Create ticket item lines.
5. Issue one pass per actual admitted person/access.

Do not create one registration per family member unless each member independently registers.

---

# 12. Paid occurrence workflow

Commerce package owns payment/order/checkout/invoice.

Events package should:

1. Mark access policy `payment_required = true`.
2. Create registration.
3. Link external order ID/type.
4. Wait for commerce event/callback.
5. Update `payment_status` as a mirror only.
6. Confirm registration and issue passes after payment success.

Do not create payment records in Events package.

---

# 13. Walk-in workflow

## Free walk-in with minimal data

1. Validate walk-in allowed.
2. Create `event_attendances` with:

```text
attendance_type = walk_in
event_registration_id = null
```

## Paid/formal walk-in

1. Create registration with:

```text
registration_type = walk_in
source = walk_in_counter
```

2. Attach payment/order reference if paid.
3. Create participant.
4. Issue pass if needed.
5. Check in attendee.

---

# 14. Pass issuance workflow

1. For each registration item, inspect ticket type.
2. Calculate admitted quantity:

```text
quantity * admits_quantity
```

3. Create `event_passes` per admitted participant/access.
4. Assign pass to participant where known.
5. Generate `pass_no`, QR/barcode.
6. If seating required, allocate or wait for seat selection.

---

# 15. Seat allocation workflow

1. Validate seat map and ticket seating option.
2. Hold selected seat/section using `event_seat_holds`.
3. On registration/payment success, convert hold to allocation.
4. Create `event_seat_allocations`.
5. Release expired holds automatically.

Reserved seat:

```text
event_seat_id filled
```

General/premium/VIP/standing area:

```text
event_seat_section_id filled
event_seat_id null
```

---

# 16. Check-in workflow

1. Resolve target occurrence/session.
2. If pass supplied, validate pass.
3. Validate access policy.
4. Validate attendance duplication rules.
5. Create or update `event_attendances`.
6. Set `checked_in_at`.
7. Create `event_attendance_logs`.
8. Mark pass `used_at` if pass is single-use.

---

# 17. Public submission workflow

1. Submitter creates `event_submissions` with target entity.
2. Payload stores proposed data.
3. If target implements `AcceptsEventSubmissions`, validate acceptance.
4. Create approval request if required.
5. Approver reviews.
6. If approved, converter creates real event records.
7. Link submission to created event.
8. Create submission log.

---

# 18. Management workflow

Use `event_management_assignments` for generic admin permissions.

Examples:

```text
User A can edit events owned by Masjid X.
Team B can approve submissions for Organization Y.
Partner C can update speakers for Event Z.
```

Do not use `event_involvements` for internal permissions.

---

# 19. User interaction workflow

Use:

```text
follows = keep me updated
bookmarks = save for later
event_responses = interested/going/maybe/not going
event_registrations = formal signup
event_attendances = actual check-in
```

Confirmed registration may be shown as “going” in UI, but do not duplicate rows unless product intentionally supports both social RSVP and formal registration.

---

# 20. Change visibility rules

Every event page must show active pinned updates.

Every occurrence page must show occurrence-specific updates plus inherited important event updates.

Registration flow must warn users about active important changes before they register.

Examples:

```text
Speaker changed
Venue changed
Time changed
Event postponed
Event cancelled
Live link added
Recording available
```

---

# Workflow checklist

- [x] Every state transition uses a service.
- [x] Important changes create `event_change_logs`.
- [ ] User-facing changes create `event_updates`. (model/table exists, but no automatic chain from lifecycle changes)
- [ ] High/critical updates create notification batches. (model/table/workflow exists, but auto-chaining is not wired)
- [x] Registration never equals attendance.
- [x] Response/going never equals registration.
- [x] Ticket type never equals issued pass.
- [x] Seat hold never equals seat allocation.
- [x] Organizer never equals manager automatically.
- [ ] Location snapshot is captured when using locationable source. (HasEventAddress contract exists, snapshotter service contract exists, but no snapshot implementation)
