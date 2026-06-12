# 02 — Database Schema

## Global schema rules

Every package table must follow these rules unless explicitly stated otherwise.

```text
Primary key: uuid id primary
Timestamps: timestampsTz() on mutable records
Single lifecycle/action times: timestampTz()
Metadata: jsonb metadata nullable where useful
No soft deletes: never add deleted_at
No database FK constraints
No cascade rules
Use indexed UUID reference columns, but do not call constrained(), foreign(), cascadeOnDelete(), nullOnDelete(), etc.
Use string code columns for statuses, types, roles, visibility, usage types, and categories.
```

### Migration style

Use:

```php
$table->uuid('id')->primary();
$table->uuid('event_id')->index();
$table->timestampTz('starts_at')->nullable();
$table->timestampsTz();
$table->jsonb('metadata')->nullable();
```

Do not use:

```php
$table->foreignId(...)->constrained();
$table->softDeletes();
$table->cascadeOnDelete();
$table->boolean('is_published');
$table->enum(...); // avoid unless host app explicitly opts in
```

## Status and code conventions

Use strings with application constants or PHP backed enums if desired at code level. Database should remain string-based.

Common visibility codes:

```text
public
unlisted
private
registered_only
attendees_only
managers_only
internal
```

Common lifecycle status codes:

```text
draft
pending_review
scheduled
published
delayed
postponed
rescheduled
cancelled
completed
archived
voided
expired
```

Common delivery mode codes:

```text
physical
online
hybrid
```

---

# A. Core event tables

## 1. `events`

Represents the parent event/program identity.

```text
events
- id uuid primary
- owner_type string nullable index
- owner_id uuid nullable index
- created_by_type string nullable index
- created_by_id uuid nullable index

- title string
- slug string nullable index
- summary text nullable
- description text nullable

- type string nullable index
- status string index
- visibility string index
- delivery_mode string nullable index
- timezone string nullable

- default_venue_id uuid nullable index

- published_at timestampTz nullable index
- cancelled_at timestampTz nullable index
- postponed_at timestampTz nullable index
- archived_at timestampTz nullable index
- completed_at timestampTz nullable index

- status_reason text nullable
- status_message text nullable

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Notes:

- `owner_type` / `owner_id` points to the entity that administratively owns the event, such as `Masjid`, `Organization`, `Team`, etc.
- Public organizer is not stored here; use `event_involvements` with role `organizer`.
- `default_venue_id` is optional convenience only. Detailed location truth is `event_locations`.

Indexes:

```text
id primary
owner_type, owner_id
created_by_type, created_by_id
slug
status
visibility
delivery_mode
default_venue_id
published_at
```

---

## 2. `event_occurrences`

Represents the actual scheduled happening of an event.

```text
event_occurrences
- id uuid primary
- event_id uuid index

- title string nullable
- slug string nullable index

- starts_at timestampTz nullable index
- ends_at timestampTz nullable index
- timezone string nullable

- status string index
- visibility string index
- delivery_mode string nullable index

- capacity integer nullable

- published_at timestampTz nullable index
- delayed_at timestampTz nullable index
- postponed_at timestampTz nullable index
- rescheduled_at timestampTz nullable index
- cancelled_at timestampTz nullable index
- completed_at timestampTz nullable index
- archived_at timestampTz nullable index

- rescheduled_from_occurrence_id uuid nullable index
- rescheduled_to_occurrence_id uuid nullable index

- status_reason text nullable
- status_message text nullable

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Notes:

- If new date is unknown, status should be `postponed` and `postponed_at` set.
- If new date is known, either update the same occurrence or create a new occurrence and link via `rescheduled_from_occurrence_id` / `rescheduled_to_occurrence_id`. For serious systems, prefer linked occurrence strategy.
- Detailed location truth is `event_locations`.

---

## 3. `event_sessions`

Represents agenda/program items inside an occurrence.

```text
event_sessions
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid index

- title string
- slug string nullable index
- summary text nullable
- description text nullable

- starts_at timestampTz nullable index
- ends_at timestampTz nullable index
- timezone string nullable

- status string index
- visibility string index
- delivery_mode string nullable index

- capacity integer nullable
- sort_order integer default 0 index

- published_at timestampTz nullable index
- delayed_at timestampTz nullable index
- postponed_at timestampTz nullable index
- cancelled_at timestampTz nullable index
- completed_at timestampTz nullable index
- archived_at timestampTz nullable index

- status_reason text nullable
- status_message text nullable

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Notes:

- Use sessions for official agenda: opening, keynote, panel, lunch, workshop, closing.
- Use itinerary tables for personalized journeys.

---

# B. Locations and facilities

## 4. `venues`

Represents reusable real places.

```text
venues
- id uuid primary
- parent_venue_id uuid nullable index

- name string
- slug string nullable index
- venue_type string nullable index

- line1 string nullable
- line2 string nullable
- city string nullable index
- district string nullable index
- state string nullable index
- postcode string(20) nullable index
- country string(2) nullable index

- latitude decimal(10,7) nullable index
- longitude decimal(10,7) nullable index
- geo_point geography nullable

- google_place_id string nullable index
- google_maps_url text nullable
- waze_url text nullable
- map_url text nullable

- phone string nullable
- email string nullable
- website_url text nullable
- directions text nullable

- geocoded_at timestampTz nullable
- geocoding_source string nullable

- status string index
- visibility string index

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Notes:

- `parent_venue_id` supports places inside places, for example a store inside a mall.
- `geo_point` is recommended for PostgreSQL/PostGIS. If PostGIS is not available, keep latitude/longitude and implement radius search at app/query layer.

---

## 5. `venue_spaces`

Represents persisted concrete sublocations inside a venue.

```text
venue_spaces
- id uuid primary
- venue_id uuid index

- name string
- code string nullable index
- space_type string nullable index

- level string nullable
- unit_no string nullable
- block string nullable
- wing string nullable

- capacity integer nullable

- latitude decimal(10,7) nullable index
- longitude decimal(10,7) nullable index
- geo_point geography nullable

- google_maps_url text nullable
- waze_url text nullable
- map_url text nullable
- directions text nullable

- status string index
- visibility string index

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Use this only when a real sublocation should be persisted and reused.

---

## 6. `venue_space_types`

Reusable shared space labels/templates.

```text
venue_space_types
- id uuid primary
- code string unique
- name string
- description text nullable
- category string nullable index
- applies_to_venue_type string nullable index
- sort_order integer default 0 index
- is_active boolean default true index
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Examples:

```text
main_hall
muslimah_hall
parking_lot
lecture_room
courtyard
imam_room
multipurpose_room
prayer_hall
registration_counter
stage
```

---

## 7. `event_locations`

Actual location assignment for event, occurrence, or session.

```text
event_locations
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- location_role string index

- locationable_type string nullable index
- locationable_id uuid nullable index

- venue_id uuid nullable index
- venue_space_id uuid nullable index
- venue_space_type_id uuid nullable index

- label string nullable

- line1 string nullable
- line2 string nullable
- city string nullable index
- district string nullable index
- state string nullable index
- postcode string(20) nullable index
- country string(2) nullable index

- level string nullable
- unit_no string nullable

- latitude decimal(10,7) nullable index
- longitude decimal(10,7) nullable index
- geo_point geography nullable

- google_place_id string nullable index
- google_maps_url text nullable
- waze_url text nullable
- map_url text nullable
- directions text nullable

- address_snapshot jsonb nullable

- geocoded_at timestampTz nullable
- geocoding_source string nullable

- visibility string index
- status string index
- sort_order integer default 0 index

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Location roles:

```text
primary
venue
session_room
registration_counter
check_in_counter
entrance
meeting_point
parking
pickup_point
booth
stage
livestream_location
online
```

Notes:

- `locationable_type` / `locationable_id` allows a domain model such as `Masjid` to be used as the location source.
- Always snapshot display address when using a locationable model.
- Use `venue_space_type_id` when selecting a shared sublocation such as main hall or Muslimah hall without persisting every masjid space.

---

## 8. `facility_types`

Reusable facility catalog.

```text
facility_types
- id uuid primary
- code string unique
- name string
- category string nullable index
- description text nullable
- icon string nullable
- sort_order integer default 0 index
- is_active boolean default true index
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Examples:

```text
toilet
male_toilet
female_toilet
parking
wheelchair_access
nursing_room
baby_changing_room
prayer_room
ablution_area
wifi
air_conditioning
food_area
lift
escalator
disabled_parking
ev_charging
```

---

## 9. `venue_facilities`

Optional verified permanent facilities for venues/spaces.

```text
venue_facilities
- id uuid primary
- venue_id uuid index
- venue_space_id uuid nullable index
- facility_type_id uuid index
- availability string index
- quantity integer nullable
- capacity integer nullable
- is_free boolean nullable
- fee_amount decimal(12,2) nullable
- currency string nullable
- location_label string nullable
- notes text nullable
- visibility string index
- verified_at timestampTz nullable
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

---

## 10. `event_facilities`

Facilities shown or relevant for a specific event/occurrence/session.

```text
event_facilities
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index
- facility_type_id uuid index
- event_location_id uuid nullable index
- availability string index
- quantity integer nullable
- capacity integer nullable
- is_free boolean nullable
- fee_amount decimal(12,2) nullable
- currency string nullable
- location_label string nullable
- notes text nullable
- visibility string index
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Availability codes:

```text
available
limited
unavailable
nearby
unknown
not_applicable
```

---

# C. Roles and involvements

## 11. `event_roles`

Reusable public event role codes.

```text
event_roles
- id uuid primary
- code string unique
- name string
- description text nullable
- sort_order integer default 0 index
- is_active boolean default true index
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Examples:

```text
speaker
headliner_speaker
organizer
co_organizer
host
sponsor
gold_sponsor
venue_partner
media_partner
mc
moderator
panelist
volunteer
vendor
facilitator
performer
security
photographer
person_in_charge
food_provider
```

---

## 12. `event_involvements`

Any entity involved in the event.

```text
event_involvements
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- involveable_type string index
- involveable_id uuid index

- event_role_id uuid nullable index
- role_code string nullable index

- status string index
- visibility string index

- prominence string nullable index
- is_featured boolean default false index
- is_primary boolean default false index

- starts_at timestampTz nullable index
- ends_at timestampTz nullable index

- replaced_by_involvement_id uuid nullable index
- replacement_reason text nullable

- notes text nullable
- sort_order integer default 0 index

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Notes:

- Use `event_role_id` when role records exist.
- Use `role_code` as denormalized stable code for faster filtering and safer app-side logic.
- `prominence` examples: `headliner`, `featured`, `supporting`, `operational`, `internal`.
- Speaker/topic/venue changes should use `prominence` and `role_code` to classify impact.

---

# D. Access, registrations, participants, tickets, passes, seating

## 13. `event_access_policies`

Defines how access works for event/occurrence/session.

```text
event_access_policies
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- registration_required boolean default false index
- approval_required boolean default false index
- payment_required boolean default false index
- ticket_required boolean default false index
- seating_required boolean default false index
- walk_in_allowed boolean default true index

- capacity integer nullable
- waitlist_enabled boolean default false index

- opens_at timestampTz nullable index
- closes_at timestampTz nullable index

- notes text nullable
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Payment is handled by a separate commerce package. `payment_required` only affects event access rules.

---

## 14. `event_registrations`

Registration header / booking / signup.

```text
event_registrations
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- registrant_type string nullable index
- registrant_id uuid nullable index

- registration_no string unique
- registration_type string index
- status string index
- source string index

- total_participants integer default 1
- total_amount decimal(12,2) nullable
- currency string nullable

- external_order_id uuid nullable index
- external_order_type string nullable index
- payment_status string nullable index

- registered_at timestampTz index
- approved_at timestampTz nullable index
- cancelled_at timestampTz nullable index
- rejected_at timestampTz nullable index
- waitlisted_at timestampTz nullable index
- expired_at timestampTz nullable index

- status_reason text nullable
- notes text nullable
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Registration types:

```text
individual
family
group
organization
walk_in
bulk_import
```

Sources:

```text
website
admin
manual
import
partner
api
kiosk
walk_in_counter
```

---

## 15. `event_registration_participants`

People included in a registration. Can be scoped directly to an occurrence or session via `event_occurrence_id` / `event_session_id`.

```text
event_registration_participants
- id uuid primary
- event_registration_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- participant_type string nullable index
- participant_id uuid nullable index

- name string nullable
- email string nullable index
- phone string nullable index

- relationship_to_registrant string nullable
- is_primary boolean default false index

- age integer nullable
- gender string nullable index

- status string index
- notes text nullable
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Notes:

- `participant_id` is nullable because family/friend/group registration may submit raw names without creating person/user records.

---

## 16. `event_registration_answers`

Custom form answers. Can belong to registration header or participant.

```text
event_registration_answers
- id uuid primary
- event_registration_id uuid index
- event_registration_participant_id uuid nullable index

- field_key string index
- question text
- answer text nullable
- answer_json jsonb nullable

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

---

## 17. `event_registration_items`

Selected ticket/access/package lines under a registration.

```text
event_registration_items
- id uuid primary
- event_registration_id uuid index
- event_ticket_type_id uuid index

- quantity integer default 1
- unit_price decimal(12,2) nullable
- total_price decimal(12,2) nullable
- currency string nullable

- external_order_item_id uuid nullable index
- external_order_item_type string nullable index

- status string index
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

---

## 18. `event_ticket_types`

Access/admission definitions. Not issued tickets.

```text
event_ticket_types
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- name string
- code string index
- description text nullable

- access_type string index
- seating_mode string nullable index

- price decimal(12,2) nullable
- currency string nullable
- quota integer nullable

- admits_quantity integer default 1
- min_quantity integer nullable
- max_quantity integer nullable

- sales_starts_at timestampTz nullable index
- sales_ends_at timestampTz nullable index

- status string index
- visibility string index
- sort_order integer default 0 index

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Access types:

```text
entry
seating
standing
package
addon
```

Seating modes:

```text
none
general_admission
reserved_seat
assigned_section
standing
```

---

## 19. `event_ticket_type_components`

Package ticket composition.

```text
event_ticket_type_components
- id uuid primary
- parent_ticket_type_id uuid index
- component_ticket_type_id uuid index
- quantity integer default 1
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Example: VIP Table Package includes VIP Seat x 8 and Meal Addon x 8.

---

## 20. `event_ticket_type_seating_options`

Allowed seating/section options for ticket types.

```text
event_ticket_type_seating_options
- id uuid primary
- event_ticket_type_id uuid index
- event_seat_section_id uuid nullable index
- seat_category string nullable index
- included_quantity integer nullable
- allowed_quantity integer nullable
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

---

## 21. `event_passes`

Actual issued access/pass/QR for a participant.

```text
event_passes
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- event_registration_id uuid nullable index
- event_registration_participant_id uuid nullable index
- event_registration_item_id uuid nullable index
- event_ticket_type_id uuid nullable index

- pass_no string unique
- qr_code string nullable unique
- barcode string nullable unique

- status string index

- issued_at timestampTz nullable index
- activated_at timestampTz nullable index
- cancelled_at timestampTz nullable index
- revoked_at timestampTz nullable index
- voided_at timestampTz nullable index
- used_at timestampTz nullable index
- expired_at timestampTz nullable index

- status_reason text nullable
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Pass statuses:

```text
pending
issued
active
used
cancelled
revoked
voided
expired
```

---

## 22. `event_seat_maps`

Seat map for event/occurrence/session.

```text
event_seat_maps
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index
- name string
- status string index
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

---

## 23. `event_seat_sections`

Sections/areas inside a seat map.

```text
event_seat_sections
- id uuid primary
- event_seat_map_id uuid index
- name string
- code string nullable index
- section_type string index
- seat_category string nullable index
- capacity integer nullable
- sort_order integer default 0 index
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Section types:

```text
seated
standing
general_area
vip_area
premium_area
```

Seat categories:

```text
general
premium
vip
standing
reserved
```

---

## 24. `event_seats`

Individual reserved seats.

```text
event_seats
- id uuid primary
- event_seat_section_id uuid index
- row_label string nullable
- seat_number string nullable
- label string
- status string index
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

---

## 25. `event_seat_holds`

Temporary holds during registration/checkout.

```text
event_seat_holds
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- event_seat_id uuid nullable index
- event_seat_section_id uuid nullable index

- holder_type string nullable index
- holder_id uuid nullable index
- event_registration_id uuid nullable index

- quantity integer default 1

- expires_at timestampTz index
- released_at timestampTz nullable index
- converted_at timestampTz nullable index

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

---

## 26. `event_seat_allocations`

Final seat/area assignment.

```text
event_seat_allocations
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- event_pass_id uuid nullable index
- event_registration_participant_id uuid nullable index

- event_seat_section_id uuid nullable index
- event_seat_id uuid nullable index

- allocation_type string index
- status string index

- allocated_at timestampTz index
- released_at timestampTz nullable index
- revoked_at timestampTz nullable index

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Allocation types:

```text
reserved_seat
general_section
standing_zone
vip_area
premium_area
```

---

# E. Attendance

## 27. `event_attendances`

Actual check-in / attendance truth.

```text
event_attendances
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid index
- event_session_id uuid nullable index

- event_registration_id uuid nullable index
- event_registration_participant_id uuid nullable index
- event_pass_id uuid nullable index

- attendee_type string nullable index
- attendee_id uuid nullable index

- attendance_type string index

- checked_in_at timestampTz nullable index
- checked_out_at timestampTz nullable index
- check_in_source string nullable index

- cancelled_at timestampTz nullable index
- corrected_at timestampTz nullable index

- notes text nullable
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Attendance types:

```text
registered
walk_in
vip
staff
speaker
invited_guest
session_attendee
```

Check-in sources:

```text
qr
manual
kiosk
admin
import
api
nfc
walk_in_counter
```

---

## 28. `event_attendance_logs`

Audit trail of attendance actions.

```text
event_attendance_logs
- id uuid primary
- event_attendance_id uuid index
- action string index
- source string nullable index
- performed_by_type string nullable index
- performed_by_id uuid nullable index
- occurred_at timestampTz index
- notes text nullable
- metadata jsonb nullable
- created_at timestampTz
```

Actions:

```text
checked_in
checked_out
re_checked_in
marked_absent
corrected
cancelled_check_in
```

---

# F. Materials, references, links, media, languages

## 29. `event_materials`

Things used/delivered/taught/shared in the event.

```text
event_materials
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- material_type string index
- material_id uuid nullable index

- usage_type string index

- title string nullable
- url text nullable

- visibility string index
- sort_order integer default 0 index
- notes text nullable
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Usage types:

```text
main_text
handout
slide
worksheet
module
recording
reading_assignment
exercise_file
downloadable_resource
```

---

## 30. `event_references`

Things cited/linked/credited/supporting the event.

```text
event_references
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- referenceable_type string nullable index
- referenceable_id uuid nullable index

- reference_type string index
- title string nullable
- url text nullable
- citation text nullable

- visibility string index
- sort_order integer default 0 index
- notes text nullable
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Reference types:

```text
citation
source
related_link
policy_document
supporting_evidence
legal_document
religious_reference
bibliography
previous_event
external_website
research_paper
```

---

## 31. `event_links`

URLs related to event/occurrence/session.

```text
event_links
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- link_type string index
- label string nullable
- url text

- visibility string index
- opens_at timestampTz nullable index
- expires_at timestampTz nullable index

- access_notes text nullable
- sort_order integer default 0 index
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Link types:

```text
official_website
registration
live_stream
recording
online_meeting
map
google_maps
waze
whatsapp
telegram
feedback_form
certificate
external_reference
```

---

## 32. `event_media`

Images, videos, audio, documents attached to event/occurrence/session.

```text
event_media
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- media_type string index
- usage_type string index

- file_id uuid nullable index
- url text nullable

- title string nullable
- caption text nullable
- alt_text text nullable

- visibility string index
- sort_order integer default 0 index
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Usage types:

```text
cover
thumbnail
poster
banner
gallery
speaker_photo
venue_photo
promo_video
highlight_video
recording
```

---

## 33. `event_languages`

Languages used by event/occurrence/session.

```text
event_languages
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- language_code string index
- usage_type string index
- is_primary boolean default false index
- sort_order integer default 0 index

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Usage types:

```text
delivery
subtitle
translation
material
interpretation
```

---

# G. Audience, eligibility, taxonomy, time expression

## 34. `event_audiences`

Soft marketing/display audience.

```text
event_audiences
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- audience_type string index
- value string index

- sort_order integer default 0 index
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Examples:

```text
level = beginner
profession = teachers
community = parents
```

---

## 35. `event_audience_profiles`

Common filterable audience profile fields.

```text
event_audience_profiles
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- is_child_friendly boolean nullable index
- min_age integer nullable index
- max_age integer nullable index

- notes text nullable
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

---

## 36. `event_eligibility_rules`

Hard restrictions / requirements.

```text
event_eligibility_rules
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- rule_type string index
- operator string index
- value string nullable
- value_json jsonb nullable
- effect string index

- message text nullable
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Rule types:

```text
gender
religion
age
membership
organization
ticket_type
invite_only
country
state
language
```

Operators:

```text
is
is_not
in
not_in
min
max
between
exists
```

Effects:

```text
allow
deny
require
inform
```

---

## 37. `event_taxonomies`

Reusable taxonomy groups.

```text
event_taxonomies
- id uuid primary
- code string unique
- name string
- description text nullable
- is_hierarchical boolean default true index
- is_active boolean default true index
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Examples:

```text
category
knowledge_field
theme
issue
topic
format
audience
level
```

---

## 38. `event_terms`

Terms inside taxonomies.

```text
event_terms
- id uuid primary
- event_taxonomy_id uuid index
- parent_id uuid nullable index
- code string index
- name string
- description text nullable
- sort_order integer default 0 index
- is_active boolean default true index
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

---

## 39. `event_classifications`

Attach terms to events/occurrences/sessions.

```text
event_classifications
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- event_taxonomy_id uuid index
- event_term_id uuid index

- taxonomy_code string nullable index
- term_code string nullable index

- is_primary boolean default false index
- weight integer nullable index
- sort_order integer default 0 index

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

---

## 40. `event_time_expressions`

Human/special time expression such as selepas Subuh.

```text
event_time_expressions
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- time_mode string index
- anchor_type string nullable index
- anchor_code string nullable index
- relation string nullable index
- offset_minutes integer nullable

- display_label string nullable

- resolver_class string nullable
- resolver_context jsonb nullable

- resolved_starts_at timestampTz nullable index
- resolved_ends_at timestampTz nullable index
- resolved_at timestampTz nullable index

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Time modes:

```text
exact
floating
relative_to_anchor
display_only
```

Example for ilmu360:

```text
anchor_type = prayer_time
anchor_code = subuh
relation = after
offset_minutes = 15
display_label = Selepas Subuh
```

---

# H. Itineraries and series

## 41. `event_itineraries`

Personalized/group/package/staff/VIP journey.

```text
event_itineraries
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index

- owner_type string nullable index
- owner_id uuid nullable index

- name string
- itinerary_type string index
- visibility string index
- status string index

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Itinerary types:

```text
public
vip
speaker
volunteer
group
package
staff
custom
```

---

## 42. `event_itinerary_items`

Items inside an itinerary.

```text
event_itinerary_items
- id uuid primary
- event_itinerary_id uuid index

- item_type string index
- event_session_id uuid nullable index

- title string nullable
- description text nullable

- starts_at timestampTz nullable index
- ends_at timestampTz nullable index

- venue_id uuid nullable index
- event_location_id uuid nullable index
- location_label string nullable

- sort_order integer default 0 index
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Item types:

```text
session
meal
travel
check_in
break
networking
prayer
transfer
briefing
free_time
custom
```

---

## 43. `event_series`

Curated or dynamic grouping of events/occurrences/sessions.

```text
event_series
- id uuid primary
- owner_type string nullable index
- owner_id uuid nullable index

- title string
- slug string nullable index
- description text nullable

- series_type string index
- status string index
- visibility string index

- is_dynamic boolean default false index
- dynamic_rule_json jsonb nullable

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Series types:

```text
event_series
occurrence_series
session_series
speaker_series
topic_series
material_series
curated_series
```

---

## 44. `event_series_items`

Items inside a series.

```text
event_series_items
- id uuid primary
- event_series_id uuid index

- seriesable_type string index
- seriesable_id uuid index

- event_id uuid nullable index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- title_override string nullable
- starts_at timestampTz nullable index
- sort_order integer default 0 index

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

---

## 45. `event_series_rules`

Optional formal dynamic series rules.

```text
event_series_rules
- id uuid primary
- event_series_id uuid index
- rule_type string index
- operator string index
- value string nullable
- value_json jsonb nullable
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

---

# I. Change management, updates, notifications

## 46. `event_change_logs`

Internal audit trail of meaningful event changes.

```text
event_change_logs
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- subject_type string index
- subject_id uuid nullable index

- change_type string index
- change_category string index

- old_value jsonb nullable
- new_value jsonb nullable

- reason text nullable
- internal_notes text nullable

- impact_level string index
- visibility string index
- requires_notification boolean default false index

- changed_by_type string nullable index
- changed_by_id uuid nullable index
- changed_at timestampTz index

- metadata jsonb nullable
- created_at timestampTz
```

Change categories:

```text
schedule
venue
lineup
content
access
ticketing
media
administration
status
```

Impact levels:

```text
low
medium
high
critical
```

---

## 47. `event_updates`

Public-facing updates users should see.

```text
event_updates
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- event_change_log_id uuid nullable index

- update_type string index
- title string
- message text

- severity string index
- visibility string index
- is_pinned boolean default false index

- starts_showing_at timestampTz nullable index
- stops_showing_at timestampTz nullable index
- published_at timestampTz nullable index
- archived_at timestampTz nullable index

- created_by_type string nullable index
- created_by_id uuid nullable index

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Update types:

```text
notice
schedule_change
venue_change
speaker_change
lineup_change
topic_change
cancellation
postponement
delay
recording_available
live_link_available
general_update
```

Severity:

```text
info
important
urgent
critical
```

---

## 48. `event_update_items`

Before/after items inside a public update.

```text
event_update_items
- id uuid primary
- event_update_id uuid index
- item_type string index
- label string
- old_value jsonb nullable
- new_value jsonb nullable
- sort_order integer default 0 index
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

---

## 49. `event_notification_batches`

A notification campaign triggered by an event update/change.

```text
event_notification_batches
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- event_update_id uuid nullable index
- event_change_log_id uuid nullable index

- notification_type string index
- title string
- message text

- audience_scope string index
- channels jsonb

- status string index

- scheduled_at timestampTz nullable index
- sent_at timestampTz nullable index
- cancelled_at timestampTz nullable index

- created_by_type string nullable index
- created_by_id uuid nullable index

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Audience scopes:

```text
registrants
attendees
followers
subscribers
managers
organizers
involved_parties
all_interested_users
custom
```

---

## 50. `event_notification_deliveries`

Per-recipient notification delivery tracking.

```text
event_notification_deliveries
- id uuid primary
- event_notification_batch_id uuid index

- recipient_type string index
- recipient_id uuid index

- channel string index
- destination string nullable

- status string index

- sent_at timestampTz nullable index
- delivered_at timestampTz nullable index
- read_at timestampTz nullable index
- failed_at timestampTz nullable index
- skipped_at timestampTz nullable index

- failure_reason text nullable
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

---

# J. Submissions, approvals, management

## 52. `event_submissions`

Untrusted/proposed event data before approval/conversion.

```text
event_submissions
- id uuid primary

- submitter_type string nullable index
- submitter_id uuid nullable index

- target_type string nullable index
- target_id uuid nullable index

- event_id uuid nullable index
- event_occurrence_id uuid nullable index

- submission_type string index
- status string index

- title string
- payload jsonb

- submitted_at timestampTz nullable index
- reviewed_at timestampTz nullable index
- reviewed_by_type string nullable index
- reviewed_by_id uuid nullable index

- approved_at timestampTz nullable index
- rejected_at timestampTz nullable index
- cancelled_at timestampTz nullable index
- rejection_reason text nullable

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Submission types:

```text
new_event
new_occurrence
event_update
occurrence_update
cancellation_request
speaker_update
media_submission
```

Statuses:

```text
draft
submitted
under_review
approved
rejected
needs_changes
cancelled
converted
```

---

## 53. `event_submission_logs`

Audit trail for submission workflow.

```text
event_submission_logs
- id uuid primary
- event_submission_id uuid index
- action string index
- performed_by_type string nullable index
- performed_by_id uuid nullable index
- notes text nullable
- metadata jsonb nullable
- created_at timestampTz
```

Actions:

```text
submitted
review_started
approved
rejected
changes_requested
resubmitted
converted_to_event
cancelled
```

---

## 54. `event_submission_attachments`

Attachments supplied with public submissions.

```text
event_submission_attachments
- id uuid primary
- event_submission_id uuid index
- file_id uuid nullable index
- url text nullable
- attachment_type string index
- title string nullable
- notes text nullable
- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Attachment types:

```text
poster
letter
approval_document
speaker_profile
venue_photo
supporting_file
```

---

## 55. `event_approval_requests`

Approval workflow for submissions/events/occurrences/updates.

```text
event_approval_requests
- id uuid primary

- approvable_type string index
- approvable_id uuid index

- target_type string nullable index
- target_id uuid nullable index

- requested_by_type string nullable index
- requested_by_id uuid nullable index

- assigned_to_type string nullable index
- assigned_to_id uuid nullable index

- status string index
- decision string nullable index
- decision_reason text nullable

- requested_at timestampTz index
- decided_at timestampTz nullable index
- cancelled_at timestampTz nullable index

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

---

## 56. `event_management_assignments`

Internal management permission assignments.

```text
event_management_assignments
- id uuid primary

- manageable_type string index
- manageable_id uuid index

- manager_type string index
- manager_id uuid index

- role string index
- permissions jsonb nullable

- starts_at timestampTz nullable index
- expires_at timestampTz nullable index
- revoked_at timestampTz nullable index

- status string index

- assigned_by_type string nullable index
- assigned_by_id uuid nullable index

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Roles:

```text
owner
admin
editor
approver
viewer
partner
contributor
moderator
```

---

# K. Generic custom attributes

## 64. `event_attributes`

Controlled extension fields for queryable domain-specific attributes.

```text
event_attributes
- id uuid primary
- event_id uuid index
- event_occurrence_id uuid nullable index
- event_session_id uuid nullable index

- key string index
- value_type string index

- value_text text nullable
- value_number decimal(18,4) nullable
- value_boolean boolean nullable
- value_date date nullable
- value_timestamp timestampTz nullable
- value_json jsonb nullable

- metadata jsonb nullable
- created_at timestampTz
- updated_at timestampTz
```

Use sparingly. First-class concepts must have real tables.

Examples:

```text
has_parking
has_wheelchair_access
requires_wudu
has_child_area
meal_provided
certificate_available
```

---

# Full table list

```text
events
event_occurrences
event_sessions

venues
venue_spaces
venue_space_types
event_locations
facility_types
venue_facilities
event_facilities

event_roles
event_involvements

event_access_policies
event_registrations
event_registration_participants
event_registration_answers
event_registration_items
event_ticket_types
event_ticket_type_components
event_ticket_type_seating_options
event_passes
event_seat_maps
event_seat_sections
event_seats
event_seat_holds
event_seat_allocations

event_attendances
event_attendance_logs

event_materials
event_references
event_links
event_media
event_languages

event_audiences
event_audience_profiles
event_eligibility_rules
event_taxonomies
event_terms
event_classifications
event_time_expressions

event_itineraries
event_itinerary_items
event_series
event_series_items
event_series_rules

event_change_logs
event_updates
event_update_items
event_notification_batches
event_notification_deliveries

event_submissions
event_submission_logs
event_submission_attachments
event_approval_requests
event_management_assignments

event_attributes
```

## Database checklist

- [ ] Every table has `uuid('id')->primary()`.
- [ ] No table has `deleted_at`.
- [ ] No migration uses `foreign()`, `constrained()`, `cascadeOnDelete()`, or `nullOnDelete()`.
- [ ] Every `*_id` reference column is indexed.
- [ ] Every polymorphic pair has indexes on both type and id, and preferably composite index.
- [ ] All lifecycle timestamp columns use `timestampTz()`.
- [ ] Normal mutable tables use `timestampsTz()`.
- [ ] Log-only tables may use `created_at` only if they are append-only; otherwise use `timestampsTz()`.
- [ ] Status/type/visibility fields are strings controlled by application constants.
- [ ] JSONB metadata is present only where extension is useful.
- [ ] First-class searchable concepts are not hidden in metadata.
