# Events Package — Lifecycle Audit & Refactoring Plan

---

## 1. Executive Summary

The `events` package has **16 tables** with inconsistent lifecycle modeling. Core problems:

- **Boolean flags** (`is_active`, `is_public`, `registration_required`, `waitlist_enabled`, `approval_required`) where status enums or mode enums already exist.
- **Missing transition timestamps** on registrations (no `confirmed_at`, `refunded_at`, `no_show_at`, `waitlisted_at`), occurrences (no `scheduled_at`, `live_at`, `completed_at`, `cancelled_at`), and event series (no lifecycle at all beyond `is_active`).
- **Inconsistent naming**: `state` vs `status` on `event_change_notices`.
- **No `archived_at`** on events despite `Archived` being a terminal status.
- **No `activated_at`** on events despite `Active` being a distinct transition from `Draft`.
- **`is_public`** on `EventPerson` should be a `visibility` enum aligned with `EventVisibility`.

**Key principle**: `status` describes lifecycle; every status transition must record a corresponding `*_at timestampTz`; never use `is_*` booleans for state that can be derived from status; `is_public` becomes `visibility` everywhere.

---

## 2. Full Inventory by Table

Legend: **S** = Status column, **T** = Transition timestamps present, **B** = Boolean flags (anti-pattern), **M** = Missing timestamps.

### 2.1 `event_series`

| Column | Type | Default | Problem |
|--------|------|---------|---------|
| `is_active` | boolean | true | **B** — boolean instead of status |
| — | — | — | **M** — no `status` column at all |
| — | — | — | **M** — no transition timestamps |

### 2.2 `events`

| Column | Type | Default | Problem |
|--------|------|---------|---------|
| `status` | string(32) | draft | **S** — good |
| `moderation_status` | string(32) | approved | **S** — good |
| `visibility` | string(32) | public | **S** — good |
| `published_at` | timestampTz | null | **T** — good |
| `public_starts_at` | timestampTz | null | **T** — good |
| `public_ends_at` | timestampTz | null | **T** — good |
| `cancelled_at` | timestampTz | null | **T** — good |
| `postponed_at` | timestampTz | null | **T** — good |
| `delayed_at` | timestampTz | null | **T** — good |
| `last_state_change_at` | timestampTz | null | **T** — audit good |
| — | — | — | **M** — no `activated_at` |
| — | — | — | **M** — no `archived_at` |
| `structure` | string(32) | standalone | OK — classification, not lifecycle |

### 2.3 `event_venues`

| Column | Type | Default | Problem |
|--------|------|---------|---------|
| `location_type` | string(32) | physical | OK — classification |
| — | — | — | **M** — no `status` (venues can be active/inactive) |

### 2.4 `event_occurrences`

| Column | Type | Default | Problem |
|--------|------|---------|---------|
| `status` | string(32) | draft | **S** — good |
| `participation_mode` | string(32) | registration_required | **S** — good |
| `registration_opens_at` | timestampTz | null | **T** — good |
| `registration_closes_at` | timestampTz | null | **T** — good |
| `check_in_opens_at` | timestampTz | null | **T** — good |
| `check_in_closes_at` | timestampTz | null | **T** — good |
| — | — | — | **M** — no `scheduled_at` |
| — | — | — | **M** — no `live_at` |
| — | — | — | **M** — no `completed_at` |
| — | — | — | **M** — no `cancelled_at` |
| `registration_mode` | string | free | OK — configuration, not lifecycle |

### 2.5 `event_registrations`

| Column | Type | Default | Problem |
|--------|------|---------|---------|
| `status` | string(32) | pending | **S** — good |
| `checked_in_at` | timestampTz | null | **T** — good |
| `cancelled_at` | timestampTz | null | **T** — good |
| — | — | — | **M** — no `confirmed_at` |
| — | — | — | **M** — no `refunded_at` |
| — | — | — | **M** — no `no_show_at` |
| — | — | — | **M** — no `waitlisted_at` |

### 2.6 `event_people` (event_speakers)

| Column | Type | Default | Problem |
|--------|------|---------|---------|
| `is_public` | boolean | true | **B** — boolean instead of `visibility` |
| — | — | — | **M** — no visibility enum |

### 2.7 `event_assets`

| Column | Type | Default | Problem |
|--------|------|---------|---------|
| `visibility` | string | public | OK — already string-based visibility |

### 2.8 `event_submissions`

| Column | Type | Default | Problem |
|--------|------|---------|---------|
| `status` | string | draft | **S** — good |
| `submitted_at` | timestampTz | null | **T** — good |

Submissions lifecycle is intermediated by reviews; no additional transition timestamps strictly required (review decisions track rationale separately).

### 2.9 `event_reviews`

| Column | Type | Default | Problem |
|--------|------|---------|---------|
| `decision` | EventModerationStatus | pending | **S** — good |
| `reviewed_at` | timestampTz | null | **T** — good |

### 2.10 `event_change_notices`

| Column | Type | Default | Problem |
|--------|------|---------|---------|
| `state` | string | draft | **Renamed to `status`** for consistency |
| `published_at` | timestampTz | null | **T** — good |
| `retracted_at` | timestampTz | null | **T** — good |

### 2.11 `event_attendance`

| Column | Type | Default | Problem |
|--------|------|---------|---------|
| `status` | string | present | **S** — good |
| `checked_in_at` | timestampTz | null | **T** — good |

### 2.12 — 2.16 `event_classifications`, `event_agenda_items`, `event_engagements`, `event_reference_assignments`, `event_sub_locations`

No lifecycle fields — classification/relational tables. No changes needed.

---

## 3. Problems Summary

| # | Problem | Affected Tables | Severity |
|---|---------|-----------------|----------|
| P1 | `is_active` boolean — no status column, no timestamps | event_series | High |
| P2 | `is_public` boolean — should be `visibility` enum | event_people | High |
| P5 | Missing `activated_at` on events | events | High |
| P6 | Missing `archived_at` on events | events | High |
| P7 | Missing `scheduled_at`, `live_at`, `completed_at`, `cancelled_at` on occurrences | event_occurrences | High |
| P8 | Missing `confirmed_at`, `refunded_at`, `no_show_at`, `waitlisted_at` on registrations | event_registrations | High |
| P9 | `state` column instead of `status` | event_change_notices | Medium |
| P10 | No `status` column on venues | event_venues | Low |

---

## 4. Recommended Structure

### 4.1 `event_series`

```
status         string(32) NOT NULL DEFAULT 'active'         -- active | inactive | archived
activated_at   timestampTz NULL
archived_at    timestampTz NULL
-- REMOVE: is_active
```

### 4.2 `events`

```
status                         string(32) NOT NULL DEFAULT 'draft'
activated_at                   timestampTz NULL   -- ADD: draft → active
cancelled_at                   timestampTz NULL   -- keep
postponed_at                   timestampTz NULL   -- keep
delayed_at                     timestampTz NULL   -- keep
archived_at                    timestampTz NULL   -- ADD: terminal
published_at                   timestampTz NULL   -- keep
public_starts_at               timestampTz NULL   -- keep
public_ends_at                 timestampTz NULL   -- keep
last_state_change_at           timestampTz NULL   -- keep
```

### 4.3 `event_venues`

```
status         string(32) NOT NULL DEFAULT 'active'         -- active | inactive
-- Optional: activated_at, deactivated_at
```

### 4.4 `event_occurrences`

```
status                         string(32) NOT NULL DEFAULT 'draft'
scheduled_at                   timestampTz NULL   -- ADD: draft → scheduled
live_at                        timestampTz NULL   -- ADD: scheduled → live
completed_at                   timestampTz NULL   -- ADD: live → completed
cancelled_at                   timestampTz NULL   -- ADD
registration_opens_at          timestampTz NULL   -- keep
registration_closes_at         timestampTz NULL   -- keep
check_in_opens_at              timestampTz NULL   -- keep
check_in_closes_at             timestampTz NULL   -- keep
```

### 4.5 `event_registrations`

```
status                 string(32) NOT NULL DEFAULT 'pending'
confirmed_at           timestampTz NULL   -- ADD: pending → confirmed
checked_in_at          timestampTz NULL   -- keep
cancelled_at           timestampTz NULL   -- keep
refunded_at            timestampTz NULL   -- ADD: cancelled → refunded
no_show_at             timestampTz NULL   -- ADD: confirmed → no_show
waitlisted_at          timestampTz NULL   -- ADD: pending → waitlisted
```

### 4.6 `event_people`

```
visibility     string(32) NOT NULL DEFAULT 'public'         -- EventVisibility enum
-- REMOVE: is_public
```

### 4.7 `event_change_notices`

```
-- RENAME: state → status
status         string(32) NOT NULL DEFAULT 'draft'
published_at   timestampTz NULL   -- keep
retracted_at   timestampTz NULL   -- keep
```

---

## 5. Refactoring Plan with Parallel Checklists

### Track A — Migrations (DB schema)

- [x] **A1**: Add `status`, `activated_at`, `archived_at` to `event_series`; remove `is_active`
- [x] **A2**: Add `activated_at`, `archived_at` to `events`
- [x] **A3**: Add `scheduled_at`, `live_at`, `completed_at`, `cancelled_at` to `event_occurrences`
- [x] **A4**: Add `confirmed_at`, `refunded_at`, `no_show_at`, `waitlisted_at` to `event_registrations`
- [x] **A5**: Add `visibility` to `event_people`; remove `is_public`
- [x] **A6**: Rename `state` → `status` on `event_change_notices`
- [x] **A7**: Add `status` to `event_venues`

### Track B — Models (casts, fillable, attributes, PHPDoc)

- [x] **B1**: `EventSeries` — update casts/fillable/PHPDoc for new columns, remove `is_active`
- [x] **B2**: `Event` — add `activated_at`, `archived_at` casts
- [x] **B3**: `Occurrence` — add `scheduled_at`, `live_at`, `completed_at`, `cancelled_at` casts
- [x] **B4**: `Registration` — add `confirmed_at`, `refunded_at`, `no_show_at`, `waitlisted_at` casts
- [x] **B5**: `EventPerson` — add `visibility` cast (EventVisibility); remove `is_public`, update `scopePubliclyVisible`
- [x] **B6**: `EventChangeNotice` — rename `state` → `status` property references
- [x] **B7**: `Venue` — add `status` cast if relevant

### Track C — Enums & Logic

- [x] **C1**: Add `SeriesStatus` enum (Active, Inactive, Archived) with transition rules
- [x] **C2**: Update `EventStatus` transitions to set `activated_at`, `archived_at`
- [x] **C3**: Add `OccurrenceStatus` transitions to set new timestamps
- [x] **C4**: Add `RegistrationStatus` transitions to set new timestamps
- [x] **C5**: Update `EventPerson` scope/accessors for `visibility` instead of `is_public`
- [x] **C6**: Update `EventChangeNotice` for `status` instead of `state`

### Track D — Tests

- [x] **D1**: Add tests for `SeriesStatus` transitions and timestamps
- [x] **D2**: Add tests for `EventStatus` `activated_at` / `archived_at` setting
- [x] **D3**: Add tests for `OccurrenceStatus` transition timestamps
- [x] **D4**: Add tests for `RegistrationStatus` transition timestamps
- [x] **D5**: Update `EventPerson` visibility tests
- [x] **D6**: Update `EventChangeNotice` status tests

---

## 6. Migration Strategy

### Phase 1: Add new columns (non-breaking)

Create a single migration that adds all new columns with NULL defaults:

```
event_series: status, activated_at, archived_at
events: activated_at, archived_at
event_occurrences: scheduled_at, live_at, completed_at, cancelled_at
event_registrations: confirmed_at, refunded_at, no_show_at, waitlisted_at
event_people: visibility
event_venues: status
event_change_notices: add new status column (alongside state), backfill
```

### Phase 2: Backfill data

Run a command or migration-step that:
1. Sets `event_series.status = 'active'` where `is_active = true`, `'archived'` otherwise
2. Sets `event_series.activated_at = created_at` for existing active series
3. Sets `event_people.visibility = 'public'` where `is_public = true`, `'private'` otherwise
4. Sets `event_change_notices.status = event_change_notices.state`

### Phase 3: Remove old columns (breaking)

A second migration that drops:
```
event_series: is_active
event_people: is_public
event_change_notices: state (after confirming status is populated)
```

### Phase 4: Add NOT NULL constraints

Once data is clean, make `status` columns NOT NULL where appropriate.

---

## 7. Verification Commands

```bash
# PHPStan on the events package
./vendor/bin/phpstan analyse packages/events/src --level=6

# Pint formatting on changed files only
./vendor/bin/pint packages/events/src --test

# Run events package tests with coverage
./vendor/bin/pest --parallel packages/events/tests

# Grep for remaining is_* booleans (should only find legacy/documented exceptions)
rg -n "is_active|is_public" packages/events/src/Models packages/events/src/Enums packages/events/database

# Grep for state instead of status on change_notices
rg -n "\bstate\b" packages/events/src/Models/EventChangeNotice.php

# Verify all status enums have corresponding *_at columns in migrations
rg -n "timestampTz\('.*_at'\)" packages/events/database/migrations/
```

---

*Generated from full audit of 12 migrations, 16 models, 9 enums. No backward compatibility required.*
