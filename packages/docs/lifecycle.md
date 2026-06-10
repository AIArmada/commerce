---
title: Docs Package — Lifecycle Audit & Refactoring Plan
package: docs
date: 2026-06-10
---

## 1. Executive Summary

The docs package has **14 tables**. Lifecycle entities (`docs`, `doc_emails`, `doc_payments`, `doc_approvals`, `doc_einvoice_submissions`) need state-transition timestamps and proper state management. Configuration entities (`docs_workflows`, `docs_sequences`, `docs_email_templates`) use `is_active` booleans — these are admin toggles and do not require lifecycle changes. `docs_doc_templates`, `docs_doc_share_links`, `docs_sequence_numbers`, `docs_versions`, `docs_doc_status_histories` have minor structural issues only.

**Core principle**: lifecycle entities get `status` + business-critical `*_at` timestampTz columns. Config entities keep `is_active` booleans as-is.

## 2. Full Inventory by Table

### 2.1 `docs_workflows`

| Column | Type | Notes |
|--------|------|-------|
| `is_active` | boolean | Admin config toggle — kept as-is |
| `created_at` | timestampTz | OK |
| `updated_at` | timestampTz | OK |

No lifecycle issues. Configuration entity.

### 2.2 `docs_workflow_steps`

| Column | Type | Notes |
|--------|------|-------|
| `is_required` | boolean | Configuration — kept as-is |
| `timeout_hours` | int|null | Configuration |
| `created_at` | timestampTz | OK |
| `updated_at` | timestampTz | OK |

**Missing**: `completed_at` (when step action executed)

### 2.3 `docs_doc_templates`

| Column | Type | Notes |
|--------|------|-------|
| `is_default` | boolean | Scoped uniqueness marker — kept as-is |
| `created_at` | timestampTz | OK |
| `updated_at` | timestampTz | OK |

No lifecycle issues. Templates are configuration.

### 2.4 `docs`

| Column | Type | Problem |
|--------|------|---------|
| `status` | string (FQCN) | Spatie model-states stores full class path — fragile to renames. Should store simple enum strings with state machine. |
| `paid_at` | timestampTz|null | OK |
| `created_at` | timestampTz | OK |
| `updated_at` | timestampTz | OK |

**Missing**: `sent_at`, `cancelled_at`, `refunded_at`, `overdue_at`

Lifecycle (8 states): `draft` → `pending` → `sent` → `overdue`/`partially_paid`/`paid` → `refunded`, with `cancelled` as terminal from almost any state. spatie/model-states is appropriate here (4+ states with guarded transitions).

### 2.5 `docs_doc_share_links`

| Column | Type | Notes |
|--------|------|-------|
| `expires_at` | timestampTz|null | Planned expiry |
| `revoked_at` | timestampTz|null | Lifecycle event |
| `last_accessed_at` | timestampTz|null | Tracking, not state change |
| `access_count` | int | OK |
| `created_at` | timestampTz | OK |
| `updated_at` | timestampTz | OK |

No issues. `revoked_at` and `expires_at` cover terminal states.

### 2.6 `docs_doc_status_histories`

| Column | Type | Problem |
|--------|------|---------|
| `status` | string (FQCN) | Same FQCN pattern as `docs.status` — should store simple enum strings |
| `changed_by` | string|null | Should reference an actor type consistently |
| `created_at` | timestampTz | OK (this IS the status change timestamp) |
| `updated_at` | timestampTz | Redundant — status history should be immutable |

**Missing**: `changed_by_type` to distinguish user/system/automation actors.

### 2.7 `docs_sequences`

| Column | Type | Notes |
|--------|------|-------|
| `is_active` | boolean | Admin config toggle — kept as-is |
| `created_at` | timestampTz | OK |
| `updated_at` | timestampTz | OK |

No lifecycle issues. Configuration entity.

### 2.8 `docs_sequence_numbers`

| Column | Type | Notes |
|--------|------|-------|
| `last_number` | bigint | OK |
| `created_at` | timestampTz | OK |
| `updated_at` | timestampTz | OK |

No issues. Counter with no lifecycle states.

### 2.9 `docs_payments`

| Column | Type | Problem |
|--------|------|---------|
| `paid_at` | timestampTz (NOT NULL) | OK |
| `created_at` | timestampTz | OK |
| `updated_at` | timestampTz | OK |

**Missing**: `refunded_at`. Payment lifecycle: created → paid → refunded.

### 2.10 `docs_email_templates`

| Column | Type | Notes |
|--------|------|-------|
| `is_active` | boolean | Admin config toggle — kept as-is |
| `created_at` | timestampTz | OK |
| `updated_at` | timestampTz | OK |

No lifecycle issues. Configuration entity.

### 2.11 `docs_emails`

| Column | Type | Problem |
|--------|------|---------|
| `status` | string | OK (enum-backed: queued/sent/failed/delivered/bounced) |
| `sent_at` | timestampTz|null | OK |
| `opened_at` | timestampTz|null | Tracking, not state change — OK |
| `clicked_at` | timestampTz|null | Tracking, not state change — OK |
| `open_count` | int | OK |
| `click_count` | int | OK |
| `created_at` | timestampTz | OK |
| `updated_at` | timestampTz | OK |

**Missing**: `delivered_at`, `failed_at` (bounced_at already exists implicitly via status; keep `sent_at` for the business-critical transition)

Email lifecycle: `queued` → `sent` → `delivered`/`failed`. `opened`/`clicked` are tracked events, not lifecycle states.

### 2.12 `docs_versions`

| Column | Type | Problem |
|--------|------|---------|
| `created_at` | timestampTz | OK (this IS the version timestamp) |
| `updated_at` | timestampTz | Redundant — versions should be immutable |

No other issues.

### 2.13 `docs_approvals`

| Column | Type | Problem |
|--------|------|---------|
| `status` | string | OK (enum-backed: pending/approved/rejected) |
| `approved_at` | timestampTz|null | OK |
| `rejected_at` | timestampTz|null | OK |
| `expires_at` | timestampTz|null | Planned deadline, not actual expiry time |
| `created_at` | timestampTz | OK |
| `updated_at` | timestampTz | OK |

**Missing**: `expired_at` (when it actually expired). Keep `expires_at` as the deadline and add `expired_at` as the actual event.

### 2.14 `docs_einvoice_submissions`

| Column | Type | Problem |
|--------|------|---------|
| `status` | string | OK (enum-backed: pending/submitted/processing/completed/failed) |
| `validation_status` | string|null | Parallel concern, not lifecycle — OK |
| `submitted_at` | timestampTz|null | OK |
| `validated_at` | timestampTz|null | OK |
| `created_at` | timestampTz | OK |
| `updated_at` | timestampTz | OK |

**Missing**: `completed_at`, `failed_at`

## 3. Problems Summary

| # | Problem | Affected Tables | Severity |
|---|---------|----------------|----------|
| P1 | Missing business-critical state-transition timestamps | docs, workflow_steps, emails, approvals, einvoice_submissions, payments | High |
| P2 | Spatie model-states stores FQCN in DB — fragile to renames; should use simple enum string storage with state machine | docs, doc_status_histories | High |
| P3 | `updated_at` on immutable records | doc_status_histories, doc_versions | Medium |
| P4 | `DocApproval.expires_at` is planned deadline, not actual expiry timestamp | doc_approvals | Medium |
| P5 | `Refunded` state exists but no `refunded_at` on `docs` and no `refunded_at` on `payments` | docs, doc_payments | Medium |
| P6 | `DocWorkflowStep` has `timeout_hours` but no `timed_out_at` or `escalated_at` | doc_workflow_steps | Low |
| P7 | `changed_by` is free-text string, no actor-type distinction | doc_status_histories | Low |
| P8 | `EmailStatus` enum has `Delivered` case but no `delivered_at` column; no `failed_at` timestamp | doc_emails | Medium |

**Not problems (config entities):** `is_active` booleans on `docs_workflows`, `docs_sequences`, `docs_email_templates` are admin configuration toggles and do not require `status` columns, state machines, or lifecycle timestamps. `is_default` on `docs_doc_templates` is a designation, not lifecycle.

## 4. Recommended Structure

### 4.1 Configuration entities (no changes)

`docs_workflows`, `docs_sequences`, `docs_email_templates`, `docs_doc_templates` keep their booleans as-is.

### 4.2 Target column layout — lifecycle entities

**`docs`**
```
status VARCHAR NOT NULL DEFAULT 'draft'
sent_at TIMESTAMPTZ NULL
cancelled_at TIMESTAMPTZ NULL
refunded_at TIMESTAMPTZ NULL
overdue_at TIMESTAMPTZ NULL
paid_at TIMESTAMPTZ NULL (keep)
created_at, updated_at (keep)
```

Keep spatie/model-states (8 states, guarded transitions). Fix FQCN storage: use custom state mapping to store simple enum strings (e.g., `'draft'` not `'AIArmada\Docs\States\Draft'`). Existing `DocStatus` enum maps to state classes.

**`docs_workflow_steps`**
```
completed_at TIMESTAMPTZ NULL
timed_out_at TIMESTAMPTZ NULL
escalated_at TIMESTAMPTZ NULL
created_at, updated_at (keep)
```

**`docs_doc_status_histories`**
```
status VARCHAR NOT NULL
changed_by VARCHAR NULL
changed_by_type VARCHAR NULL DEFAULT 'user'   -- user, system, automation
created_at (keep)
DROP updated_at
```

Store simple enum strings matching `docs.status`, not FQCNs.

**`docs_payments`**
```
status VARCHAR NOT NULL DEFAULT 'paid'       -- pending, paid, refunded
refunded_at TIMESTAMPTZ NULL
paid_at, created_at, updated_at (keep)
```

**`docs_emails`**
```
status VARCHAR NOT NULL DEFAULT 'queued'
delivered_at TIMESTAMPTZ NULL
failed_at TIMESTAMPTZ NULL
sent_at, opened_at, clicked_at (keep)
created_at, updated_at (keep)
```

**`docs_versions`**
```
created_at (keep)
DROP updated_at
```

**`docs_approvals`**
```
status VARCHAR NOT NULL DEFAULT 'pending'
approved_at, rejected_at (keep)
expires_at TIMESTAMPTZ NULL      -- keep as planned deadline
expired_at TIMESTAMPTZ NULL      -- ADD: actual expiry event
created_at, updated_at (keep)
```

**`docs_einvoice_submissions`**
```
status VARCHAR NOT NULL DEFAULT 'pending'
completed_at TIMESTAMPTZ NULL
failed_at TIMESTAMPTZ NULL
submitted_at, validated_at (keep)
validation_status (keep — parallel concern)
created_at, updated_at (keep)
```

### 4.3 Status enums

| Table | Enum | Notes |
|-------|------|-------|
| `docs` | `DocStatus` (Draft, Pending, Sent, PartiallyPaid, Paid, Overdue, Cancelled, Refunded) | Backed by spatie/model-states; store simple strings |
| `docs_payments` | `PaymentStatus` (Pending, Paid, Refunded) | Simple BackedEnum (3 linear states) |
| `docs_emails` | `EmailStatus` (Queued, Sent, Delivered, Failed) | Simple BackedEnum |
| `docs_approvals` | `DocApprovalStatus` (Pending, Approved, Rejected, Expired) | Add `Expired` case |
| `docs_einvoice_submissions` | `DocEInvoiceSubmissionStatus` (Pending, Submitted, Processing, Completed, Failed) | Existing |

## 5. Refactoring Plan — Parallel-Agent Checklist

### Agent A: Core Docs Table (`docs`)

- [x] Create migration: add `sent_at`, `cancelled_at`, `refunded_at`, `overdue_at` columns to `docs`
- [x] Backfill `sent_at`, `cancelled_at` from `doc_status_histories.created_at` where applicable
- [x] Convert `status` column from Spatie FQCN to simple string enum values (keep state machine, fix storage format)
- [x] Update `Doc` model: keep `HasStates`, configure custom state mapping for simple string storage, add `status` enum cast
- [x] Update `Doc::markAsPaid()` — set `paid_at`; add `refunded_at` support
- [x] Update `Doc::markAsSent()` — set `sent_at`
- [x] Update `Doc::cancel()` — set `cancelled_at`
- [x] Update `Doc::updateStatus()` — set `overdue_at`
- [x] Update `DocStatusHistory` model: store simple strings, not FQCNs
- [x] Update `DocStatusHistory` migration: drop `updated_at`

### Agent B: Workflow Steps

- [x] Create migration: add `completed_at`, `timed_out_at`, `escalated_at` to `docs_workflow_steps`
- [x] Update `DocWorkflowStep` model with new casts

### Agent C: Emails

- [x] Create migration: add `delivered_at`, `failed_at` to `docs_emails`
- [x] Update `DocEmail` model with new casts

### Agent D: Payments

- [x] Create migration: add `status`, `refunded_at` to `docs_payments`; backfill `status = 'paid'` where `paid_at IS NOT NULL`
- [x] Create `PaymentStatus` enum
- [x] Update `DocPayment` model with new casts
- [x] Add `markAsRefunded()` method to `DocPayment`

### Agent E: Approvals

- [x] Create migration: add `expired_at` to `docs_approvals`
- [x] Add `Expired` case to `DocApprovalStatus` enum
- [x] Update `DocApproval` model: add `expired_at` cast
- [x] Update `isExpired()` to set `expired_at` when evaluating

### Agent F: E-Invoice Submissions

- [x] Create migration: add `completed_at`, `failed_at` to `docs_einvoice_submissions`
- [x] Update `DocEInvoiceSubmission` model with new casts

### Agent G: Status History & Cleanup

- [x] Create migration: add `changed_by_type` to `docs_doc_status_histories`; backfill `changed_by_type = 'user'`
- [x] Drop `updated_at` from `docs_doc_status_histories`
- [x] Update `DocStatusHistory` model
- [x] Drop `updated_at` from `docs_versions`
- [x] Update `DocVersion` model

### Agent H: Cross-cutting Verification

- [x] Run PHPStan on `packages/docs/src --level=6`
- [x] Run Pint on `packages/docs/src`
- [x] Run Pest: `./vendor/bin/pest --parallel packages/docs/tests`
- [x] Grep for remaining Spatie FQCN references
- [x] Update `config/docs.php` if needed
- [x] Update docs in `packages/docs/docs/`

---

## 6. Migration Strategy

### Phase 1: Add columns (non-breaking)
All `ADD COLUMN` migrations run first. New columns are nullable with no default.

### Phase 2: Backfill data
- Spatie FQCN → simple enum strings (keep state machine functional)
- Derived timestamps from `doc_status_histories` → new `*_at` columns on `docs`

### Phase 3: Drop columns, switch casts
- Drop `updated_at` from status_histories and versions
- Switch model casts

### Phase 4: Verify
- Run full test suite
- Deploy

**No backward compatibility** is maintained. All consumers (Filament resources, actions, API controllers, jobs) must be updated simultaneously.

---

## 7. Verification Commands

```bash
# Per-package PHPStan
./vendor/bin/phpstan analyse packages/docs/src --level=6

# Per-package tests (parallel)
./vendor/bin/pest --parallel packages/docs/tests

# Grep for remaining boolean status checks on config tables
rg -n -- "is_active\b" packages/docs/src --include='*.php'

# Grep for remaining Spatie FQCN references in status columns
rg -n -- "States\\\\" packages/docs/database

# Grep for remaining is_* booleans that should be timestamps
rg -n -- "is_paid|is_canceled|is_refunded|is_sent" packages/docs/src --include='*.php'

# Verify all business-critical *_at columns exist
rg -n -- "_at" packages/docs/database/migrations

# Cross-tenant regression tests
./vendor/bin/pest --parallel packages/docs/tests --filter=OwnerScoping

# Run the full docs test suite
./vendor/bin/pest --parallel packages/docs/tests
```
