# Filament Events — Lifecycle

## 1. Overview

`filament-events` surfaces lifecycle fields from `aiarmada/events` across six Filament resources: EventSeries, Event, Occurrence, Registration, Venue, and EventSubLocation. All lifecycle rules and state transitions are enforced by `aiarmada/events` — Filament reads canonical fields and adds no lifecycle logic of its own.

---

## 2. Filament Resource Lifecycle Field Consistency

### 2.1 EventResource

| Field | Form | Table | Infolist | Gap |
|---|---|---|---|---|
| `status` | select | badge col | badge entry | OK |
| `moderation_status` | select | badge col | badge entry | OK |
| `visibility` | select | badge col | badge entry | OK |
| `published_at` | datetime | — | datetime | Table missing |
| `public_starts_at` | datetime | — | datetime | Table missing |
| `public_ends_at` | datetime | — | datetime | Table missing |

**Gap**: `published_at`, `public_starts_at`, `public_ends_at` are form/infolist only — not in the event table. These are secondary visibility gates but may be useful as toggleable columns.

### 2.2 OccurrenceResource

| Field | Form | Table | Infolist | Gap |
|---|---|---|---|---|
| `status` | select | badge col | badge entry | OK |
| `participation_mode` | select | badge col | badge entry | OK |
| `starts_at` | datetime | datetime col | datetime | OK |
| `ends_at` | datetime | — | datetime | Table missing |
| `timezone` | text | text col | text entry | OK |
| `registration_opens_at` | datetime | — | datetime | Table missing |
| `registration_closes_at` | datetime | — | datetime | Table missing |
| `check_in_opens_at` | datetime | — | datetime | Table missing |
| `check_in_closes_at` | datetime | — | datetime | Table missing |
| `waitlist_enabled` | toggle | badge col (hidden default) | badge entry | OK |
| `approval_required` | toggle | badge col (hidden default) | badge entry | OK |

### 2.3 RegistrationResource

| Field | Form | Table | Infolist | Gap |
|---|---|---|---|---|
| `status` | select | badge col | badge entry | OK |
| `attendance_source` | select | badge col | badge entry | OK |
| `checked_in_at` | datetime | datetime col | datetime | OK |
| `cancelled_at` | datetime | — | datetime | **Gap**: missing from table |

### 2.4 OccurrencesRelationManager

**Gap**: Table omits `approval_required` and `waitlist_enabled` columns from the occurrence listing within event context.

---

## 3. Section Naming Inconsistencies

| Resource | Form Section | Infolist Section |
|---|---|---|
| EventResource | "Settings" | (various) |
| OccurrenceResource | "Registration Policy" / "Registration Window" | "Registration Window" / "Registration Policy" (matches form) |
| RegistrationResource | **"Lifecycle"** | "Registration" (no dedicated lifecycle section) |

Only `RegistrationForm` uses the label "Lifecycle" for a section — no other form uses this terminology. The infolist places `checked_in_at` and `cancelled_at` inside the "Registration" section alongside status and attendance source.

---

## 4. Enum Option Helper Duplication (DRY)

`EventForm`, `EventTable`, and `EventResource` each define their own copies of the same `collect(Cases::cases())->mapWithKeys(...)` pattern for enum options instead of calling `EventResource::statusOptions()`. This duplication exists across:

- `EventForm::statusOptions()` / `::moderationStatusOptions()` / `::visibilityOptions()` — should call `EventResource::statusOptions()` etc.
- `EventTable` filters — same duplication

The Occurrence and Registration resources do not have this problem — their forms and tables use `OccurrenceResource::statusOptions()` / `RegistrationResource::statusOptions()` consistently.

---

## 5. Navigation Badge Caching Inconsistency

| Resource | Navigation Badge Strategy |
|---|---|
| EventResource | `OwnerCache::remember()` — cached per owner |
| EventSeriesResource | Not evaluated |
| OccurrenceResource | Uncached query |
| RegistrationResource | Uncached query |
| VenueResource | Not evaluated |

Only `EventResource` uses owner-scoped caching for its navigation badge count. The cache key is derived from the current owner context via `OwnerCache`, so badge counts do not bleed across owners. Other resources still run uncached counts on every page load.

---

## 6. Action Coverage

| Resource | Lifecycle Actions | Gap |
|---|---|---|
| EventResource | Submit for Review, Approve, Request Changes, Reject | Full moderation workflow covered |
| OccurrenceResource | (none exposed) | No state transition actions (draft→scheduled→live→completed) |
| RegistrationResource | Check In, Approve, Reject, Cancel | Covered in table, view page, and relation manager |

---

## 7. Verification Commands

```bash
# 1. PHPStan on filament-events
./vendor/bin/phpstan analyse packages/filament-events/src --level=6

# 2. Verify enum helper DRY — EventForm should delegate to EventResource
rg -n "statusOptions\|moderationStatusOptions\|visibilityOptions" packages/filament-events/src/Resources/EventResource/

# 3. Verify OwnerUiScope is applied consistently
rg -n "OwnerUiScope::apply" packages/filament-events/src/Resources/

# 4. Run filament-events tests
./vendor/bin/pest --parallel packages/filament-events/tests/

# 5. Pint formatting
./vendor/bin/pint packages/filament-events/src --test
```
