# Events Package Addendum 13 — Optional Enhancements with Event / Occurrence / Session Scope Parity

## Purpose

This document records the **nine optional enhancements** that may be added to the generic Events package after the core implementation is stable.

These items must **not** be treated as Phase 1 blockers unless the product explicitly requires them immediately. They exist to make the package future-ready while preserving clean boundaries with Engagement, Analytics, Commerce, Authorization, and application-specific domain packages.

## Non-Negotiable Scope Rule

Any capability added to the Events package must work consistently across:

```text
events
├── event_occurrences
└── event_sessions
```

If a feature works for an event, it must also be able to work for an occurrence and a session.

Use the standard scope pattern unless a more specific polymorphic design is justified:

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
= applies only to one occurrence

event_id filled, occurrence filled, session filled
= applies only to one session
```

For more generic features, use a polymorphic target plus denormalized event scope columns:

```text
target_type
target_id
event_id nullable
event_occurrence_id nullable
event_session_id nullable
```

This keeps the feature flexible while still making event queries efficient.

## Package-Wide Rules

All tables in this addendum must follow the package standards:

```text
- UUID primary keys.
- No database foreign keys.
- No database cascading.
- No soft deletes.
- Use timestampTz / timestampsTz where appropriate.
- Use lifecycle timestamps instead of boolean state where meaningful.
- Use codes/enums at the application layer, not database constraints.
- Use application services for integrity, validation, conflict detection, authorization, and cleanup.
- Integrate with external packages through contracts, adapters, hooks, Laravel events/listeners, and class_exists checks where appropriate.
```

Do not add `deleted_at`. Use lifecycle fields such as:

```text
archived_at
cancelled_at
voided_at
expired_at
revoked_at
superseded_at
published_at
approved_at
rejected_at
```

---

# The 9 Optional Enhancements

## 1. Recurrence Rules

### Goal

Support recurring patterns such as:

```text
Every Monday 8:00 PM
Every Friday after Maghrib
Daily during Ramadan
First Saturday of every month
Every two weeks
```

### Core Principle

A recurrence rule is only a **pattern**.

The actual scheduled instances must still be materialized as:

```text
event_occurrences
```

Do not treat recurrence rules as a replacement for occurrences.

```text
recurrence rule = pattern
occurrence = actual scheduled event instance
```

### Scope Parity

Recurrence should support:

```text
- Recurring event occurrences.
- Recurring sessions inside an occurrence or across occurrences.
- Recurring itinerary items if needed later.
```

### Proposed Table: `event_recurrence_rules`

```text
event_recurrence_rules
- id uuid primary

- event_id uuid nullable
- event_occurrence_id uuid nullable
- event_session_id uuid nullable

- recurrence_target_type nullable
- recurrence_target_id nullable

- code nullable
- name nullable
- description nullable

- recurrence_type
- frequency
- interval

- days_of_week jsonb nullable
- days_of_month jsonb nullable
- months_of_year jsonb nullable

- starts_on date nullable
- ends_on date nullable
- max_occurrences integer nullable

- timezone

- time_mode nullable
- starts_at_time time nullable
- ends_at_time time nullable

- anchor_type nullable
- anchor_code nullable
- relation nullable
- offset_minutes integer nullable

- rrule_text nullable
- human_readable_rule nullable

- status
- visibility

- generated_until timestampTz nullable
- last_generated_at timestampTz nullable
- disabled_at timestampTz nullable
- archived_at timestampTz nullable

- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

### Example Codes

`recurrence_type`:

```text
fixed_time
relative_to_anchor
manual_pattern
external_rrule
```

`frequency`:

```text
daily
weekly
monthly
yearly
custom
```

`anchor_type` examples:

```text
prayer_time
sunrise_sunset
business_hours
custom_domain_anchor
```

### Services

```php
interface EventOccurrenceGenerator
{
    public function generateFromRule(EventRecurrenceRule $rule, array $options = []): iterable;
}

interface EventRecurrenceRuleParser
{
    public function parse(array|string $input): EventRecurrenceRuleData;
}
```

### Acceptance Checklist

```text
[ ] Rules can generate event_occurrences.
[ ] Rules can generate event_sessions when scoped to sessions.
[ ] Recurrence generation is idempotent.
[ ] Regeneration does not duplicate occurrences.
[ ] Relative time anchors can be resolved by domain packages.
[ ] Generated occurrences keep normal lifecycle fields.
[ ] Cancelling one occurrence does not cancel the recurrence rule unless explicitly requested.
[ ] Disabling a recurrence rule does not delete generated occurrences.
```

---

## 2. Conflict Detection and Availability

### Goal

Detect scheduling conflicts for:

```text
venues
venue spaces
speakers
moderators
panelists
organizers
rooms
equipment
online links
staff
```

Examples:

```text
Speaker already booked at the same time.
Venue space already used.
Masjid has another kuliah in the same hall.
Moderator is assigned to two panels.
```

### Core Principle

Conflict detection should be mostly service-driven. Only persist availability blocks if the system needs manual blackout dates or explicit unavailable periods.

### Scope Parity

Conflict detection must work for:

```text
- whole events
- specific occurrences
- specific sessions
```

### Proposed Table: `event_availability_blocks`

```text
event_availability_blocks
- id uuid primary

- blockable_type
- blockable_id

- event_id uuid nullable
- event_occurrence_id uuid nullable
- event_session_id uuid nullable

- block_type
- reason nullable
- notes nullable

- starts_at timestampTz
- ends_at timestampTz
- timezone

- status
- visibility

- created_by_type nullable
- created_by_id nullable

- released_at timestampTz nullable
- expired_at timestampTz nullable
- archived_at timestampTz nullable

- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

### Example `block_type`

```text
unavailable
reserved
maintenance
private_booking
blackout
travel_time
preparation_time
cleanup_time
```

### Services

```php
interface EventConflictDetector
{
    public function detectConflicts(mixed $target, array $context = []): array;
}

interface EventAvailabilityChecker
{
    public function isAvailable(mixed $blockable, DateTimeInterface $startsAt, DateTimeInterface $endsAt, array $context = []): bool;
}

interface EventScheduleValidator
{
    public function validateSchedule(mixed $target): EventScheduleValidationResult;
}
```

### Acceptance Checklist

```text
[ ] Can detect venue conflicts.
[ ] Can detect venue_space conflicts.
[ ] Can detect speaker/moderator/panelist conflicts through event_involvements.
[ ] Can detect event-level, occurrence-level, and session-level conflicts.
[ ] Can create warning-level conflicts without blocking save.
[ ] Can create hard-blocking conflicts where configured.
[ ] Does not require database foreign keys.
[ ] Does not cascade delete blocks.
```

---

## 3. Event Templates and Cloning

### Goal

Support creating events, occurrences, and sessions from reusable templates or from existing records.

Examples:

```text
Clone last week's kuliah occurrence.
Create a new Ramadan lecture from a template.
Clone speaker/material/location/session setup.
Create an online webinar from a standard template.
```

### Scope Parity

Templates and cloning must work for:

```text
- events
- occurrences
- sessions
```

### Proposed Table: `event_templates`

```text
event_templates
- id uuid primary

- owner_type nullable
- owner_id nullable

- templateable_type nullable
- templateable_id nullable

- code nullable
- name
- description nullable

- template_type
- status
- visibility

- payload jsonb
- default_scope jsonb nullable

- created_by_type nullable
- created_by_id nullable

- published_at timestampTz nullable
- archived_at timestampTz nullable

- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

### Proposed Table: `event_template_items`

```text
event_template_items
- id uuid primary

- event_template_id uuid

- item_type
- item_key nullable
- payload jsonb

- sort_order integer

- status
- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

### Example `template_type`

```text
event
occurrence
session
registration_setup
location_setup
speaker_lineup
full_program
```

### Services

```php
interface EventTemplateService
{
    public function createFromTemplate(EventTemplate $template, array $overrides = []): mixed;
}

interface EventCloneService
{
    public function cloneEvent(Event $event, array $options = []): Event;

    public function cloneOccurrence(EventOccurrence $occurrence, array $options = []): EventOccurrence;

    public function cloneSession(EventSession $session, array $options = []): EventSession;
}
```

### Acceptance Checklist

```text
[ ] Can clone an event without copying old registrations/attendance.
[ ] Can clone an occurrence with optional sessions, locations, materials, links, involvements.
[ ] Can clone a session with optional speaker/material/reference setup.
[ ] Can create an event from template.
[ ] Can create an occurrence from template.
[ ] Can create a session from template.
[ ] Cloning records change logs where appropriate.
[ ] Cloning never copies stale lifecycle timestamps like cancelled_at or completed_at unless explicitly requested.
```

---

## 4. Revisions and Draft Versioning

### Goal

Support editorial workflows such as:

```text
draft version
submitted version
approved version
published version
rejected version
superseded version
```

Useful when public submissions or partner-edited events require approval before changing public data.

### Scope Parity

Revisions must work for:

```text
- event records
- occurrence records
- session records
- scoped records such as location, involvement, material, ticket, link, media, update
```

### Proposed Table: `event_revisions`

```text
event_revisions
- id uuid primary

- revisable_type
- revisable_id

- event_id uuid nullable
- event_occurrence_id uuid nullable
- event_session_id uuid nullable

- version_no integer
- revision_type

- status

- title nullable
- summary nullable

- payload jsonb
- diff jsonb nullable

- submitted_by_type nullable
- submitted_by_id nullable
- reviewed_by_type nullable
- reviewed_by_id nullable

- submitted_at timestampTz nullable
- approved_at timestampTz nullable
- rejected_at timestampTz nullable
- published_at timestampTz nullable
- superseded_at timestampTz nullable
- archived_at timestampTz nullable

- rejection_reason nullable
- internal_notes nullable

- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

### Example `revision_type`

```text
draft
proposed_change
public_submission
admin_revision
import_revision
rollback_snapshot
```

### Services

```php
interface EventRevisionManager
{
    public function createRevision(mixed $revisable, array $payload, array $context = []): EventRevision;

    public function approveRevision(EventRevision $revision, mixed $approver = null): void;

    public function publishRevision(EventRevision $revision): mixed;

    public function rejectRevision(EventRevision $revision, string $reason, mixed $reviewer = null): void;
}
```

### Acceptance Checklist

```text
[ ] Can draft changes without changing published event data.
[ ] Can draft changes for occurrences.
[ ] Can draft changes for sessions.
[ ] Can approve and publish a revision.
[ ] Can reject a revision with reason.
[ ] Publishing a revision creates event_change_logs.
[ ] Material changes create event_updates where necessary.
[ ] No database-level cascading is used.
```

---

## 5. Search Indexing Integration

### Goal

Allow external search packages to index event data without making Events responsible for search storage.

Search should support fields such as:

```text
title
summary
description
speaker
moderator
venue
masjid
organizer
topic
category
bidang ilmu
tema
isu
language
delivery mode
status
visibility
location
coordinates
date/time
eligibility
facilities
```

### Core Principle

Do not create search index tables in the Events package unless the application has no dedicated search package.

Prefer contracts and document builders.

### Scope Parity

Search documents must be buildable for:

```text
- events
- occurrences
- sessions
```

### Contracts

```php
interface EventSearchDocumentBuilder
{
    public function buildForEvent(Event $event): array;

    public function buildForOccurrence(EventOccurrence $occurrence): array;

    public function buildForSession(EventSession $session): array;
}

interface EventSearchIndexer
{
    public function index(mixed $target): void;

    public function remove(mixed $target): void;
}
```

### Optional Table: `event_search_documents`

Only create this table if there is no external search package.

```text
event_search_documents
- id uuid primary

- searchable_type
- searchable_id

- event_id uuid nullable
- event_occurrence_id uuid nullable
- event_session_id uuid nullable

- document_type
- title nullable
- summary nullable
- body nullable

- keywords jsonb nullable
- facets jsonb nullable
- coordinates jsonb nullable

- indexed_at timestampTz nullable
- stale_at timestampTz nullable

- status
- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

### Events to Dispatch

```text
EventPublished
EventUpdated
EventCancelled
EventOccurrencePublished
EventOccurrenceUpdated
EventSessionPublished
EventClassificationChanged
EventLocationChanged
EventInvolvementChanged
EventMaterialChanged
```

### Acceptance Checklist

```text
[ ] External search can index events.
[ ] External search can index occurrences.
[ ] External search can index sessions.
[ ] Search document includes scoped location and coordinates.
[ ] Search document includes speakers/involvements.
[ ] Search document includes taxonomy/classifications.
[ ] Search document excludes private or managers-only data.
[ ] Search integration works even if search package is not installed by using a Null adapter.
```

---

## 6. Translation and Localization

### Goal

Support multilingual event content.

Examples:

```text
Malay title
English title
Arabic description
Chinese summary
Tamil notes
```

### Core Principle

Languages are already represented by `event_languages`, but translated text may need its own structure.

If a translation package exists, integrate with it.

If no translation package exists, use generic event translations.

### Scope Parity

Translations must work for:

```text
- events
- occurrences
- sessions
- scoped records where needed, such as event_updates, event_locations, event_materials
```

### Proposed Table: `event_translations`

```text
event_translations
- id uuid primary

- translatable_type
- translatable_id

- event_id uuid nullable
- event_occurrence_id uuid nullable
- event_session_id uuid nullable

- locale
- field
- value text

- status

- translated_by_type nullable
- translated_by_id nullable

- translated_at timestampTz nullable
- approved_at timestampTz nullable
- rejected_at timestampTz nullable
- published_at timestampTz nullable
- archived_at timestampTz nullable

- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

### Contracts

```php
interface EventTranslationProvider
{
    public function translate(mixed $target, string $field, string $locale): ?string;
}

interface HasEventTranslations
{
    public function eventTranslations();
}
```

### Acceptance Checklist

```text
[ ] Can translate event title/summary/description.
[ ] Can translate occurrence title/summary/description.
[ ] Can translate session title/summary/description.
[ ] Can translate public updates where needed.
[ ] Translation visibility respects event visibility.
[ ] Rejected translations do not display publicly.
[ ] External translation package can replace this table through adapter.
```

---

## 7. Import and Export

### Goal

Support controlled imports and exports for:

```text
events
occurrences
sessions
locations
involvements
materials
registrations
attendance
```

Examples:

```text
Import weekly kuliah schedule from spreadsheet.
Import 500 occurrences.
Export attendance list.
Export registrations.
Export upcoming events by masjid.
```

### Scope Parity

Import/export must work for:

```text
- events
- occurrences
- sessions
- scoped child records
```

### Proposed Table: `event_import_jobs`

```text
event_import_jobs
- id uuid primary

- owner_type nullable
- owner_id nullable

- import_type
- source_type
- source_name nullable
- source_path nullable

- status

- total_rows integer nullable
- processed_rows integer nullable
- successful_rows integer nullable
- failed_rows integer nullable

- started_at timestampTz nullable
- completed_at timestampTz nullable
- failed_at timestampTz nullable
- cancelled_at timestampTz nullable

- error_summary nullable
- options jsonb nullable
- metadata jsonb nullable

- created_by_type nullable
- created_by_id nullable

- created_at timestampTz
- updated_at timestampTz
```

### Proposed Table: `event_import_job_rows`

```text
event_import_job_rows
- id uuid primary

- event_import_job_id uuid

- row_number integer
- row_key nullable

- status
- payload jsonb
- mapped_payload jsonb nullable
- errors jsonb nullable

- imported_type nullable
- imported_id nullable

- processed_at timestampTz nullable
- failed_at timestampTz nullable

- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

### Optional Table: `event_export_jobs`

```text
event_export_jobs
- id uuid primary

- owner_type nullable
- owner_id nullable

- export_type
- target_type nullable
- target_id nullable

- event_id uuid nullable
- event_occurrence_id uuid nullable
- event_session_id uuid nullable

- status

- file_path nullable
- file_url nullable

- started_at timestampTz nullable
- completed_at timestampTz nullable
- failed_at timestampTz nullable
- expired_at timestampTz nullable

- filters jsonb nullable
- columns jsonb nullable
- metadata jsonb nullable

- created_by_type nullable
- created_by_id nullable

- created_at timestampTz
- updated_at timestampTz
```

### Services

```php
interface EventImporter
{
    public function import(EventImportJob $job): void;
}

interface EventExporter
{
    public function export(EventExportJob $job): void;
}

interface EventImportValidator
{
    public function validateRow(array $row, array $context = []): array;
}
```

### Acceptance Checklist

```text
[ ] Can import events.
[ ] Can import occurrences.
[ ] Can import sessions.
[ ] Can import locations/involvements/materials when mapped.
[ ] Failed rows are inspectable.
[ ] Successful rows point to imported records by type/id.
[ ] Export can target events, occurrences, or sessions.
[ ] No import process bypasses lifecycle/change logging where relevant.
```

---

## 8. Data Quality and Verification

### Goal

Support trust and verification for event data.

Examples:

```text
verified event
verified occurrence
verified session
verified venue
verified speaker line-up
verified organizer
verified live link
verified location coordinates
```

### Core Principle

Verification is different from approval.

```text
approval = allowed to publish
verification = confirmed to be accurate/trusted
```

### Scope Parity

Verification must work for:

```text
- events
- occurrences
- sessions
- selected scoped child records such as locations, links, involvements, materials
```

### Proposed Table: `event_verifications`

```text
event_verifications
- id uuid primary

- verifiable_type
- verifiable_id

- event_id uuid nullable
- event_occurrence_id uuid nullable
- event_session_id uuid nullable

- verification_type
- status
- confidence_level nullable

- source_type nullable
- source_id nullable
- source_label nullable
- source_url nullable

- verified_by_type nullable
- verified_by_id nullable

- verified_at timestampTz nullable
- rejected_at timestampTz nullable
- expired_at timestampTz nullable
- revoked_at timestampTz nullable

- rejection_reason nullable
- notes nullable
- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

### Example `verification_type`

```text
identity
location
coordinates
schedule
speaker_lineup
organizer
material
reference
live_link
recording
```

### Example `confidence_level`

```text
low
medium
high
official
```

### Services

```php
interface EventVerificationService
{
    public function verify(mixed $target, string $verificationType, array $context = []): EventVerification;

    public function revoke(EventVerification $verification, string $reason): void;
}

interface EventTrustScoreCalculator
{
    public function score(mixed $target): int|float;
}
```

### Acceptance Checklist

```text
[ ] Can verify an event.
[ ] Can verify an occurrence.
[ ] Can verify a session.
[ ] Can verify event location coordinates.
[ ] Can verify speaker line-up.
[ ] Can revoke verification without deleting records.
[ ] Public UI can display verified badges from this table.
[ ] Verification does not replace approval workflow.
```

---

## 9. Moderation and Reporting

### Goal

Support reports, flags, moderation decisions, and abuse handling for public/crowdsourced events.

Examples:

```text
fake event
wrong speaker
wrong venue
spam submission
inappropriate media
misleading title
cancelled event still listed
broken live link
```

### Core Principle

If there is a dedicated Moderation package, Events should integrate through contracts instead of creating these tables.

If no Moderation package exists, Events may provide lightweight moderation tables.

### Scope Parity

Moderation must work for:

```text
- events
- occurrences
- sessions
- scoped child records such as media, links, submissions, updates
```

### Proposed Table: `event_reports`

```text
event_reports
- id uuid primary

- reportable_type
- reportable_id

- event_id uuid nullable
- event_occurrence_id uuid nullable
- event_session_id uuid nullable

- reporter_type nullable
- reporter_id nullable

- report_type
- status
- severity

- title nullable
- message nullable

- reviewed_by_type nullable
- reviewed_by_id nullable

- reported_at timestampTz
- reviewed_at timestampTz nullable
- resolved_at timestampTz nullable
- rejected_at timestampTz nullable
- archived_at timestampTz nullable

- resolution nullable
- internal_notes nullable
- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

### Proposed Table: `event_moderation_actions`

```text
event_moderation_actions
- id uuid primary

- event_report_id nullable

- actionable_type
- actionable_id

- event_id uuid nullable
- event_occurrence_id uuid nullable
- event_session_id uuid nullable

- action_type
- status

- reason nullable
- notes nullable

- performed_by_type nullable
- performed_by_id nullable

- performed_at timestampTz nullable
- reversed_at timestampTz nullable
- expired_at timestampTz nullable

- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

### Example `report_type`

```text
wrong_information
fake_event
spam
inappropriate_content
broken_link
wrong_location
wrong_speaker
copyright_issue
safety_issue
```

### Example `action_type`

```text
hide
unpublish
request_changes
mark_under_review
remove_media
disable_link
warn_owner
escalate
restore
```

### Contracts

```php
interface EventModerationService
{
    public function report(mixed $target, array $data): EventReport;

    public function moderate(mixed $target, string $action, array $context = []): EventModerationAction;
}

interface ReportableForEvents
{
    public function eventReportTitle(): string;
}
```

### Acceptance Checklist

```text
[ ] Can report events.
[ ] Can report occurrences.
[ ] Can report sessions.
[ ] Can report event links/media/updates/submissions.
[ ] Can hide or unpublish without deleting.
[ ] Can reverse moderation actions.
[ ] Can integrate with external moderation package.
[ ] Moderation actions create event_change_logs where relevant.
```

---

# Cross-Cutting Implementation Requirements

## 1. All Optional Modules Must Respect Scope Parity

Every new feature must ask:

```text
Can this apply to an event?
Can this apply to an occurrence?
Can this apply to a session?
```

If yes, use:

```text
event_id
event_occurrence_id nullable
event_session_id nullable
```

If the target can be more generic, use:

```text
target_type / target_id
```

plus denormalized scope columns for querying.

## 2. All Optional Modules Must Respect Existing Package Boundaries

Do not duplicate responsibilities from other packages.

```text
Commerce owns payments, orders, checkouts, invoices, refunds.
Engagement owns follows, bookmarks, responses, subscriptions, reminders, reactions, shares.
Analytics owns views, clicks, opens, impressions, funnels, metrics.
Authorization owns permissions, policies, roles, gates where applicable.
Laravel Notifications can handle notification delivery.
Domain packages own domain meaning such as Masjid, Kitab, Speaker, Course, Hotel, School.
```

## 3. All Optional Modules Must Use Hooks and Contracts

Each optional module must expose:

```text
- contracts
- services
- Laravel events
- Null adapters where external package is optional
- Filament resources only when module is enabled
```

## 4. Do Not Make Phase 1 Too Large

Recommended implementation order:

```text
Phase A: Search indexing contracts only, no table.
Phase B: Recurrence rules and occurrence generation.
Phase C: Conflict detection services.
Phase D: Templates/cloning.
Phase E: Revisions if approval-heavy workflow is required.
Phase F: Import/export if admin bulk operation is required.
Phase G: Verification/moderation if public submissions are enabled.
Phase H: Translation only when multilingual content is required.
```

---

# Parallel Agent Checklist

## Agent 1 — Recurrence

```text
[ ] Implement event_recurrence_rules migration.
[ ] Implement EventRecurrenceRule model.
[ ] Implement EventOccurrenceGenerator contract.
[ ] Add tests for event/occurrence/session scope.
[ ] Ensure generated occurrences are idempotent.
```

## Agent 2 — Availability and Conflict Detection

```text
[ ] Implement event_availability_blocks migration.
[ ] Implement availability/conflict contracts.
[ ] Detect conflicts for venue, space, and involvement.
[ ] Add tests for event/occurrence/session conflicts.
```

## Agent 3 — Templates and Cloning

```text
[ ] Implement event_templates migration.
[ ] Implement event_template_items migration.
[ ] Implement clone services.
[ ] Add tests ensuring registrations/attendance are not cloned by default.
```

## Agent 4 — Revisions

```text
[ ] Implement event_revisions migration.
[ ] Implement EventRevisionManager.
[ ] Connect publishing revisions to event_change_logs.
[ ] Add tests for event/occurrence/session revisions.
```

## Agent 5 — Search Integration

```text
[ ] Implement EventSearchDocumentBuilder contract.
[ ] Implement NullEventSearchIndexer.
[ ] Dispatch indexing events from lifecycle actions.
[ ] Do not add search table unless explicitly enabled.
```

## Agent 6 — Translation

```text
[ ] Implement event_translations migration only if no external translation package is used.
[ ] Implement EventTranslationProvider.
[ ] Add tests for event/occurrence/session translated fields.
```

## Agent 7 — Import / Export

```text
[ ] Implement event_import_jobs migration.
[ ] Implement event_import_job_rows migration.
[ ] Implement optional event_export_jobs migration.
[ ] Add row-level validation and failure tracking.
```

## Agent 8 — Verification

```text
[ ] Implement event_verifications migration.
[ ] Implement EventVerificationService.
[ ] Add verified badge query helpers.
[ ] Add tests for revoking verification.
```

## Agent 9 — Moderation

```text
[ ] Implement event_reports migration only if no external moderation package is available.
[ ] Implement event_moderation_actions migration only if no external moderation package is available.
[ ] Implement EventModerationService.
[ ] Ensure moderation actions do not delete records.
```

---

# Final Instruction to Developer Agents

These nine additions are **optional modules**, not excuses to redesign the core Events package.

They must extend the existing design, not replace it.

The existing package philosophy remains:

```text
Events package stores event truth.
Engagement package stores intentional user engagement.
Analytics package stores passive behavior and metrics.
Commerce package stores money/order/payment truth.
Authorization package controls permission.
Domain packages provide real-world meaning.
```

Every enhancement must preserve:

```text
- event / occurrence / session scope parity
- UUID primary keys
- timestampTz lifecycle fields
- no soft deletes
- no database foreign keys
- no cascading
- application-side integrity
- modular optional implementation
```

Do not build a monster. Build clean optional modules. Database jangan jadi kenduri kahwin yang semua lauk masuk satu periuk.
