# Affiliates Package — Lifecycle Field Audit

> **Audit date**: 2026-06-10
> **Package**: `aiarmada/affiliates`
> **Backward compatibility**: NOT required (beta, breaking changes allowed per AGENTS.md)

---

## Executive Summary

The affiliates package is in **good shape** compared to a typical legacy codebase. It already uses `status` as the primary lifecycle field for all major entities, has state machines via `spatie/model-states`, and uses timestamp fields for most state transitions (`approved_at`, `paid_at`, `reviewed_at`, `verified_at`, `completed_at`, `occurred_at`).

**Six problems exist:**

1. **`is_active` boolean on AffiliateLink** — should become `deactivated_at` timestamp (user confirmed: links have usage history)
2. **`is_public` boolean on AffiliateProgram** — should become `visibility` string column
3. **`is_verified` boolean on AffiliatePayoutMethod** — redundant with `verified_at`, should be removed
4. **Missing timestamp columns** — `deactivated_at`, `paused_at`, `archived_at`, `rejected_at` for models that have corresponding states but no transition timestamps
5. **No enum for Membership status** — `affiliate_program_memberships.status` needs `MembershipStatus` BackedEnum
6. **No enum for FraudSignal status** — `affiliate_fraud_signals.status` needs `FraudSignalStatus` BackedEnum

**No `is_published`, `is_cancelled`, `is_archived`, `is_approved` booleans exist** — the package already uses timestamps correctly for these concepts.

**Entities that intentionally keep `is_active` boolean** (admin configuration toggles, not lifecycle):
- `affiliate_commission_rules.is_active` — already has `starts_at`/`ends_at` time windows; the time window IS the lifecycle; `is_active` is just an admin override toggle
- `affiliate_commission_templates.is_active` — config toggle
- `affiliate_training_modules.is_active` — config toggle

---

## Full Inventory by Table

### 1. `affiliates` — Affiliate (core model)

| Column | Type | Current Meaning | Problem? |
|--------|------|----------------|----------|
| `status` | string (state machine) | Lifecycle: draft/pending/active/paused/disabled | None — correct |
| `activated_at` | timestampTz nullable | When affiliate was activated | None — correct |
| *(missing)* | — | No `deactivated_at` | **Missing** — state machine has Disabled |
| *(missing)* | — | No `paused_at` | **Missing** — state machine has Paused |

**Current `isActive()` logic** (`Affiliate.php:298`):
```php
public function isActive(): bool
{
    return $this->status instanceof Active;
}
```
This is correct — `isActive()` delegates to the state machine.

**Problem**: When an affiliate transitions to `Disabled` or `Paused`, there's no timestamp recording *when* that happened. The `activated_at` column gets set on activation, but `deactivated_at` and `paused_at` are missing.

---

### 2. `affiliate_programs` — AffiliateProgram

| Column | Type | Current Meaning | Problem? |
|--------|------|----------------|----------|
| `status` | string (enum) | Lifecycle: draft/active/paused/archived | None — correct |
| `is_public` | boolean | Public visibility toggle | **Problem** — boolean for visibility |
| `starts_at` | timestampTz nullable | Program start date | None — correct |
| `ends_at` | timestampTz nullable | Program end date | None — correct |
| *(missing)* | — | No `archived_at` | **Missing** — enum has Archived |
| *(missing)* | — | No `paused_at` | **Missing** — enum has Paused |

**Current `isActive()` logic** (`AffiliateProgram.php:159`):
```php
public function isActive(): bool
{
    if ($this->status !== ProgramStatus::Active) { return false; }
    if ($this->starts_at && $this->starts_at->isFuture()) { return false; }
    if ($this->ends_at && $this->ends_at->isPast()) { return false; }
    return true;
}
```
Timing-aware: correct approach.

**`is_public`**: Used as a simple yes/no toggle for discoverability. Should become `visibility` for scalability.

---

### 3. `affiliate_conversions` — AffiliateConversion

| Column | Type | Current Meaning | Problem? |
|--------|------|----------------|----------|
| `status` | string (state machine) | Lifecycle: pending/qualified/approved/rejected/paid | None — correct |
| `approved_at` | timestampTz nullable | When conversion was approved | None — correct |
| `occurred_at` | timestampTz nullable | When conversion occurred | None — correct |
| *(missing)* | — | No `rejected_at` | **Missing** — state has Rejected |
| *(missing)* | — | No `paid_at` | **Missing** — state has Paid |

**State transitions exist but timestamps are missing for `Rejected` and `Paid`.** The `approved_at` is handled correctly in `booted()`.

---

### 4. `affiliate_payouts` — AffiliatePayout

| Column | Type | Current Meaning | Problem? |
|--------|------|----------------|----------|
| `status` | string (state machine) | Lifecycle: pending/processing/completed/failed/cancelled | None — correct |
| `scheduled_at` | timestampTz nullable | When payout is scheduled | None — correct |
| `paid_at` | timestampTz nullable | When payout was completed | None — correct (set in UpdatePayoutStatus) |
| *(missing)* | — | No `cancelled_at` | **Missing** — state has Cancelled |
| *(missing)* | — | No `failed_at` | **Missing** — state has Failed |

---

### 5. `affiliate_program_memberships` — AffiliateProgramMembership

| Column | Type | Current Meaning | Problem? |
|--------|------|----------------|----------|
| `status` | string (no BackedEnum) | Lifecycle: pending/approved/rejected/suspended | **Problem** — needs `MembershipStatus` BackedEnum |
| `applied_at` | timestampTz | When applied | None — correct |
| `approved_at` | timestampTz nullable | When approved | None — correct |
| `approved_by` | foreignUuid nullable | Who approved | None — correct |
| `expires_at` | timestampTz nullable | Expiry date | None — correct |
| *(missing)* | — | No `rejected_at` | **Missing** — status has Rejected |
| *(missing)* | — | No `suspended_at` | **Missing** — status has Suspended |

---

### 6. `affiliate_links` — AffiliateLink

| Column | Type | Current Meaning | Problem? |
|--------|------|----------------|----------|
| `is_active` | boolean default true | Enable/disable toggle | **Problem** — boolean, no timestamp |

**Recommendation**: Replace with `deactivated_at timestampTz nullable`. Add `isEnabled(): bool` accessor.

---

### 7. `affiliate_commission_rules` — AffiliateCommissionRule

| Column | Type | Current Meaning | Problem? |
|--------|------|----------------|----------|
| `is_active` | boolean default true | Admin enable/disable toggle | None — config toggle, keep boolean |
| `starts_at` | timestampTz nullable | Rule start date | None — correct |
| `ends_at` | timestampTz nullable | Rule end date | None — correct |

**Current `isActive()` logic** (`AffiliateCommissionRule.php:82`):
```php
public function isActive(): bool
{
    if (! $this->is_active) { return false; }
    if ($this->starts_at && $this->starts_at->isFuture()) { return false; }
    if ($this->ends_at && $this->ends_at->isPast()) { return false; }
    return true;
}
```
`is_active` here is an admin override toggle. The `starts_at`/`ends_at` time window IS the lifecycle. No changes needed.

---

### 8. `affiliate_commission_templates` — AffiliateCommissionTemplate

| Column | Type | Current Meaning | Problem? |
|--------|------|----------------|----------|
| `is_active` | boolean default true | Admin enable/disable toggle | None — config toggle, keep boolean |
| `is_default` | boolean default false | Default template flag | None — config flag, keep boolean |

---

### 9. `affiliate_training_modules` — AffiliateTrainingModule

| Column | Type | Current Meaning | Problem? |
|--------|------|----------------|----------|
| `is_active` | boolean default true | Admin enable/disable toggle | None — config toggle, keep boolean |
| `is_required` | boolean default false | Required flag | None — config flag, keep boolean |

---

### 10. `affiliate_training_progress` — AffiliateTrainingProgress

| Column | Type | Current Meaning | Problem? |
|--------|------|----------------|----------|
| `completed_at` | timestampTz nullable | When module completed | None — correct |
| `quiz_passed_at` | timestampTz nullable | When quiz passed | None — correct |

No issues.

---

### 11. `affiliate_fraud_signals` — AffiliateFraudSignal

| Column | Type | Current Meaning | Problem? |
|--------|------|----------------|----------|
| `status` | string (no BackedEnum) | Lifecycle: detected/reviewed/dismissed/confirmed | **Problem** — needs `FraudSignalStatus` BackedEnum |
| `detected_at` | timestampTz | When detected | None — correct |
| `reviewed_at` | timestampTz nullable | When reviewed | None — correct |
| `reviewed_by` | foreignUuid nullable | Who reviewed | None — correct |
| *(missing)* | — | No `dismissed_at` | **Missing** — status has Dismissed |
| *(missing)* | — | No `confirmed_at` | **Missing** — status has Confirmed |

---

### 12. `affiliate_payout_methods` — AffiliatePayoutMethod

| Column | Type | Current Meaning | Problem? |
|--------|------|----------------|----------|
| `is_verified` | boolean default false | Verified flag | **Problem** — redundant with `verified_at` |
| `is_default` | boolean default false | Default method | Acceptable |
| `verified_at` | timestampTz nullable | When verified | None — correct |

**`verify()` method** (`AffiliatePayoutMethod.php:83`):
```php
public function verify(): void
{
    $this->update([
        'is_verified' => true,
        'verified_at' => now(),
    ]);
}
```
Both fields are set simultaneously, but they can drift. `is_verified` should be removed; the model should expose `isVerified()` via the timestamp.

---

### 13. `affiliate_payout_holds` — AffiliatePayoutHold

| Column | Type | Current Meaning | Problem? |
|--------|------|----------------|----------|
| `released_at` | timestampTz nullable | When hold was released | None — correct |
| `expires_at` | timestampTz nullable | Hold expiry | None — correct |
| `placed_by` | foreignUuid nullable | Who placed hold | None — correct |

No issues. Correct pattern.

---

### 14. `affiliate_tax_documents` — AffiliateTaxDocument

| Column | Type | Current Meaning | Problem? |
|--------|------|----------------|----------|
| `status` | string (no BackedEnum) | Lifecycle: pending/generated/sent/failed | **Problem** — needs `TaxDocumentStatus` BackedEnum |
| `generated_at` | timestampTz nullable | When generated | None — correct |
| `sent_at` | timestampTz nullable | When sent | None — correct |

---

### 15. Other tables — no lifecycle issues

- `affiliate_attributions`, `affiliate_touchpoints` — tracking timestamps only, no lifecycle
- `affiliate_balances` — financial data, no lifecycle
- `affiliate_ranks`, `affiliate_rank_histories` — configuration/audit data, no lifecycle
- `affiliate_network` — closure table, no lifecycle
- `affiliate_daily_stats` — aggregated stats, no lifecycle
- `affiliate_program_tiers`, `affiliate_program_creatives` — configuration data
- `affiliate_volume_tiers`, `affiliate_commission_promotions` — configuration/rules data
- `affiliate_support_tickets`, `affiliate_support_messages` — support system with `status` string (acceptable for support context)
- `affiliate_payout_events` — audit log

---

## Problems Summary

### Problem A: `is_active` boolean → `deactivated_at` timestamp (1 model)

- `affiliate_links.is_active`

**Excluded** (config toggles, keep boolean):
- `affiliate_commission_rules.is_active` — `starts_at`/`ends_at` already provide time-window lifecycle
- `affiliate_commission_templates.is_active` — admin config toggle
- `affiliate_training_modules.is_active` — admin config toggle

### Problem B: `is_public` boolean → `visibility` string (1 model)

- `affiliate_programs.is_public`

### Problem C: `is_verified` boolean → remove, use `verified_at` only (1 model)

- `affiliate_payout_methods.is_verified`

### Problem D: Missing transition timestamps (7 models)

| Model | Missing columns |
|-------|----------------|
| Affiliate | `deactivated_at`, `paused_at` |
| AffiliateConversion | `rejected_at`, `paid_at` |
| AffiliatePayout | `cancelled_at`, `failed_at` |
| AffiliateProgramMembership | `rejected_at`, `suspended_at` |
| AffiliateProgram | `archived_at`, `paused_at` |
| AffiliateFraudSignal | `dismissed_at`, `confirmed_at` |
| AffiliateLink | `deactivated_at` |

### Problem E: Missing BackedEnums for status columns (3 models)

- `affiliate_program_memberships.status` — needs `MembershipStatus` BackedEnum
- `affiliate_fraud_signals.status` — needs `FraudSignalStatus` BackedEnum
- `affiliate_tax_documents.status` — needs `TaxDocumentStatus` BackedEnum

---

## Recommended Structure

### Affiliate

```
status              — draft, pending, active, paused, disabled
activated_at         — when activated
deactivated_at       — when deactivated (NEW)
paused_at            — when paused (NEW)
```

### AffiliateProgram

```
status              — draft, active, paused, archived
visibility          — public, private (NEW, replaces is_public)
starts_at           — (existing)
ends_at             — (existing)
paused_at           — when paused (NEW)
archived_at         — when archived (NEW)
```

### AffiliateConversion

```
status              — pending, qualified, approved, rejected, paid
occurred_at         — (existing)
approved_at         — (existing)
rejected_at         — when rejected (NEW)
paid_at             — when paid (NEW)
```

### AffiliatePayout

```
status              — pending, processing, completed, failed, cancelled
scheduled_at        — (existing)
paid_at             — (existing)
failed_at           — when failed (NEW)
cancelled_at        — when cancelled (NEW)
```

### AffiliateProgramMembership

```
status              — pending, approved, rejected, suspended (backed by MembershipStatus enum, NEW)
applied_at          — (existing)
approved_at         — (existing)
approved_by         — (existing)
rejected_at         — when rejected (NEW)
suspended_at        — when suspended (NEW)
expires_at          — (existing)
```

### AffiliateLink

```
deactivated_at      — when deactivated (NEW, replaces is_active)
```

### AffiliateFraudSignal

```
status              — detected, reviewed, dismissed, confirmed (backed by FraudSignalStatus enum, NEW)
detected_at         — (existing)
reviewed_at         — (existing)
reviewed_by         — (existing)
dismissed_at        — when dismissed (NEW)
confirmed_at        — when confirmed (NEW)
```

### AffiliatePayoutMethod

```
verified_at         — (existing, keep)
is_verified         — REMOVE (redundant)
is_default          — (existing, keep)
```

### AffiliateTaxDocument

```
status              — pending, generated, sent, failed (backed by TaxDocumentStatus enum, NEW)
generated_at        — (existing)
sent_at             — (existing)
```

---

## Refactoring Plan — Parallel-Agent Checklist

Each section below is **independently executable by a separate agent**. Sections can be worked on in parallel.

---

## Phase 1: Foundation (Sequential — must complete before Phase 2)

### Section 1.0: Create New BackedEnums

- [x] **1.0.1** Create `MembershipStatus` enum
  - File: `src/Enums/MembershipStatus.php`
  - Cases: `Pending = 'pending'`, `Approved = 'approved'`, `Rejected = 'rejected'`, `Suspended = 'suspended'`
  - Include `label()` and `color()` methods

- [x] **1.0.2** Create `FraudSignalStatus` enum
  - File: `src/Enums/FraudSignalStatus.php`
  - Cases: `Detected = 'detected'`, `Reviewed = 'reviewed'`, `Dismissed = 'dismissed'`, `Confirmed = 'confirmed'`
  - Include `label()` and `color()` methods

- [x] **1.0.3** Create `TaxDocumentStatus` enum
  - File: `src/Enums/TaxDocumentStatus.php`
  - Cases: `Pending = 'pending'`, `Generated = 'generated'`, `Sent = 'sent'`, `Failed = 'failed'`
  - Include `label()` and `color()` methods

---

## Phase 2: Model-Specific Refactors (All parallel)

### Section 2.1: Affiliate — Missing transition timestamps

**Files to modify:**
- `database/migrations/2000_01_01_000001_create_affiliates_table.php` → add `deactivated_at`, `paused_at`
- `src/Models/Affiliate.php` → add `deactivated_at`, `paused_at` to `$fillable`, `casts`, `getAuditInclude()`
- `src/Actions/Affiliates/ApproveAffiliate.php` → verify no changes needed (already sets `activated_at`)
- `src/Actions/Affiliates/RejectAffiliate.php` → add `deactivated_at = now()`
- `src/Actions/Affiliates/CreateAffiliate.php` → verify correct
- `src/States/AffiliateStatus.php` → state transitions already correct

**Checklist:**
- [x] **2.1.1** Add migration: `$table->timestampTz('deactivated_at')->nullable()` after `activated_at`
- [x] **2.1.2** Add migration: `$table->timestampTz('paused_at')->nullable()` after `deactivated_at`
- [x] **2.1.3** Add `deactivated_at`, `paused_at` to model `$fillable`
- [x] **2.1.4** Add `deactivated_at`, `paused_at` to model `casts()` as `'datetime'`
- [x] **2.1.5** Add `deactivated_at`, `paused_at` to `getAuditInclude()`
- [x] **2.1.6** Update `RejectAffiliate.php` to set `deactivated_at = now()`
- [x] **2.1.7** Create new action `PauseAffiliate` that sets `status = Paused` and `paused_at = now()`
- [x] **2.1.8** Create new action `DisableAffiliate` that sets `status = Disabled` and `deactivated_at = now()`
- [x] **2.1.9** Update `ApproveAffiliate.php` to also clear `deactivated_at` and `paused_at` to null on reactivation
- [x] **2.1.10** Update `Affiliate::isActive()` — no change needed (delegates to state)
- [x] **2.1.11** Add `isDeactivated()` accessor: `return $this->deactivated_at !== null`
- [x] **2.1.12** Add `isPaused()` accessor: `return $this->paused_at !== null`

---

### Section 2.2: AffiliateProgram — `is_public` → `visibility`, missing timestamps

**Files to modify:**
- `database/migrations/2000_01_01_000017_create_affiliate_programs_table.php`
- `src/Models/AffiliateProgram.php`
- `src/Enums/ProgramStatus.php`

**Checklist:**
- [x] **2.2.1** Add migration: rename `is_public` → add `visibility` column
  - `$table->string('visibility', 32)->default('private')->after('status')`
  - Data migration: `UPDATE ... SET visibility = CASE WHEN is_public = true THEN 'public' ELSE 'private' END`
  - Then drop `is_public`
- [x] **2.2.2** Create `ProgramVisibility` enum
  - File: `src/Enums/ProgramVisibility.php`
  - Cases: `Public = 'public'`, `Private = 'private'`
- [x] **2.2.3** Update model `$fillable`: remove `is_public`, add `visibility`
- [x] **2.2.4** Update model `$casts`: remove `is_public`, add `visibility` => `ProgramVisibility::class`
- [x] **2.2.5** Update model `$attributes`: remove `'is_public' => true`, add `'visibility' => ProgramVisibility::Private`
- [x] **2.2.6** Update `isOpen()`: `return $this->isActive() && $this->visibility === ProgramVisibility::Public`
- [x] **2.2.7** Update `scopePublic()`: `return $query->where('visibility', ProgramVisibility::Public)`
- [x] **2.2.8** Add migration: `$table->timestampTz('paused_at')->nullable()` for paused_at
- [x] **2.2.9** Add migration: `$table->timestampTz('archived_at')->nullable()` for archived_at
- [x] **2.2.10** Add `paused_at`, `archived_at` to model `$fillable`
- [x] **2.2.11** Add `paused_at`, `archived_at` to model `$casts` as `'datetime'`
- [x] **2.2.12** Add `isArchived()` accessor: `return $this->archived_at !== null`
- [x] **2.2.13** Keep `ProgramStatus` enum as-is; just add timestamp tracking for paused/archived transitions

---

### Section 2.3: AffiliateConversion — Missing transition timestamps

**Checklist:**
- [x] **2.3.1** Add migration: `$table->timestampTz('rejected_at')->nullable()` after `approved_at`
- [x] **2.3.2** Add migration: `$table->timestampTz('paid_at')->nullable()` after `rejected_at`
- [x] **2.3.3** Add `rejected_at`, `paid_at` to model `$fillable`
- [x] **2.3.4** Add `rejected_at`, `paid_at` to model `casts()` as `'datetime'`
- [x] **2.3.5** Update `booted()` — when status transitions to RejectedConversion, set `rejected_at = now()`
- [x] **2.3.6** Update `booted()` — when status transitions to PaidConversion, set `paid_at = now()`
- [x] **2.3.7** Add `isRejected()` accessor: `return $this->rejected_at !== null`
- [x] **2.3.8** Add `isPaid()` accessor: `return $this->paid_at !== null`

---

### Section 2.4: AffiliatePayout — Missing transition timestamps

**Checklist:**
- [x] **2.4.1** Add migration: `$table->timestampTz('failed_at')->nullable()` after `paid_at`
- [x] **2.4.2** Add migration: `$table->timestampTz('cancelled_at')->nullable()` after `failed_at`
- [x] **2.4.3** Add `failed_at`, `cancelled_at` to model `$fillable`
- [x] **2.4.4** Add `failed_at`, `cancelled_at` to model `casts()` as `'datetime'`
- [x] **2.4.5** Update `UpdatePayoutStatus.php` — set `failed_at = now()` when transitioning to FailedPayout
- [x] **2.4.6** Update `UpdatePayoutStatus.php` — set `cancelled_at = now()` when transitioning to CancelledPayout
- [x] **2.4.7** Add `isFailed()` accessor: `return $this->failed_at !== null`
- [x] **2.4.8** Add `isCancelled()` accessor: `return $this->cancelled_at !== null`

---

### Section 2.5: AffiliateProgramMembership — Missing transition timestamps + MembershipStatus enum

**Checklist:**
- [x] **2.5.1** Add migration: `$table->timestampTz('rejected_at')->nullable()` after `approved_by`
- [x] **2.5.2** Add migration: `$table->timestampTz('suspended_at')->nullable()` after `rejected_at`
- [x] **2.5.3** Add `rejected_at`, `suspended_at` to model `$fillable`
- [x] **2.5.4** Add `rejected_at`, `suspended_at` to model `$casts` as `'datetime'`
- [x] **2.5.5** Update `reject()`: set `rejected_at = now()`
- [x] **2.5.6** Update `suspend()`: set `suspended_at = now()`
- [x] **2.5.7** Update `approve()`: clear `rejected_at`, `suspended_at` to null
- [x] **2.5.8** Add `isRejected()` accessor: `return $this->rejected_at !== null`
- [x] **2.5.9** Add `isSuspended()` accessor: `return $this->suspended_at !== null`
- [x] **2.5.10** Update model `$casts`: add `'status'` => `MembershipStatus::class`
- [x] **2.5.11** Replace string status comparisons with enum comparisons throughout model/actions

---

### Section 2.6: AffiliateLink — `is_active` → `deactivated_at`

**Checklist:**
- [x] **2.6.1** Add migration: add `$table->timestampTz('deactivated_at')->nullable()` before `created_at`
- [x] **2.6.2** Data migration: `UPDATE ... SET deactivated_at = NOW() WHERE is_active = false`
- [x] **2.6.3** Drop `is_active` column
- [x] **2.6.4** Update model `$fillable`: remove `is_active`, add `deactivated_at`
- [x] **2.6.5** Update model `$casts`: remove `is_active`, add `deactivated_at` => `'datetime'`
- [x] **2.6.6** Update `getAuditInclude()`: remove `is_active`, add `deactivated_at`
- [x] **2.6.7** Add `isEnabled()` accessor: `return $this->deactivated_at === null`
- [x] **2.6.8** Add `deactivate()` method: `$this->update(['deactivated_at' => now()])`
- [x] **2.6.9** Add `activate()` method: `$this->update(['deactivated_at' => null])`
- [x] **2.6.10** Update `getLoggableAttributes()`: remove `is_active`, add `deactivated_at`
- [x] **2.6.11** Search for all usages of `is_active` on this model and update

---

### Section 2.7: AffiliatePayoutMethod — Remove `is_verified`, keep `verified_at`

**Checklist:**
- [x] **2.7.1** Add migration: drop `is_verified` column
  - Keep `verified_at` — it already provides the boolean meaning
- [x] **2.7.2** Update model `$fillable`: remove `is_verified`
- [x] **2.7.3** Update model `$casts`: remove `is_verified`
- [x] **2.7.4** Update `getAuditInclude()`: remove `is_verified`
- [x] **2.7.5** Add `isVerified()` accessor: `return $this->verified_at !== null`
- [x] **2.7.6** Update `verify()`: remove `'is_verified' => true` line, keep `verified_at = now()`
- [x] **2.7.7** Search for all usages of `is_verified` on this model and update to `isVerified()`

---

### Section 2.8: AffiliateTaxDocument — TaxDocumentStatus enum + model updates

**Checklist:**
- [x] **2.8.1** Create `TaxDocumentStatus` enum (from Phase 1 Section 1.0.3)
- [x] **2.8.2** Update model `$casts`: add `'status'` => `TaxDocumentStatus::class`
- [x] **2.8.3** Add `isGenerated()`, `isSent()`, `isFailed()` accessors
- [x] **2.8.4** Add `markAsGenerated()`, `markAsSent()`, `markAsFailed()` methods

---

### Section 2.9: AffiliateFraudSignal — FraudSignalStatus enum + missing timestamps

**Checklist:**
- [x] **2.9.1** Create `FraudSignalStatus` enum (from Phase 1 Section 1.0.2)
- [x] **2.9.2** Add migration: `$table->timestampTz('dismissed_at')->nullable()` after `reviewed_by`
- [x] **2.9.3** Add migration: `$table->timestampTz('confirmed_at')->nullable()` after `dismissed_at`
- [x] **2.9.4** Add `dismissed_at`, `confirmed_at` to model `$fillable`
- [x] **2.9.5** Add `dismissed_at`, `confirmed_at` to model `$casts` as `'datetime'`
- [x] **2.9.6** Update model `$casts`: add `'status'` => `FraudSignalStatus::class`
- [x] **2.9.7** Replace string status comparisons with enum comparisons throughout model/actions
- [x] **2.9.8** Update `dismiss()`: set `dismissed_at = now()`
- [x] **2.9.9** Update `confirm()`: set `confirmed_at = now()`

---

## Phase 3: Cross-Cutting Changes (Parallel after Phase 2)

### Section 3.1: Config updates

- [x] **3.1.1** Add `visibility` defaults for programs if needed

---

### Section 3.2: Consistency Audit (run after all Phase 2 is done)

- [x] **3.2.1** Grep for remaining `is_active` on AffiliateLink: `rg "is_active" packages/affiliates/src/Models/AffiliateLink.php` — should return zero
- [x] **3.2.2** Grep for remaining `is_public` usage: `rg "is_public" packages/affiliates/src` — should return zero
- [x] **3.2.3** Grep for remaining `is_verified` on PayoutMethod: `rg "is_verified" packages/affiliates/src/Models/AffiliatePayoutMethod.php` — should return zero
- [x] **3.2.4** Grep for `disabled_at` (legacy naming): `rg "disabled_at" packages/affiliates/src packages/affiliates/database` — should return zero
- [x] **3.2.5** Verify PHPStan passes: `./vendor/bin/phpstan analyse packages/affiliates/src --level=6`
- [x] **3.2.6** Verify Pint passes: `./vendor/bin/pint packages/affiliates --test`

---

## Migration Strategy

For each `is_*` → `*_at` conversion:

1. **Add new column** (nullable timestampTz)
2. **Backfill data**: for existing records where `is_* = false`, set `*_at = NOW()` (or `created_at`)
3. **Deploy code** that writes to both old and new columns
4. **Drop old column** (no backward compatibility needed per instructions)

For missing timestamps (e.g., `deactivated_at` on Affiliate):

1. **Add new column** (nullable timestampTz)
2. **No backfill needed** — existing records were never in that state
3. Future transitions will populate the column

---

## Verification Commands

After each section completes, run:

```bash
# Per model check
./vendor/bin/phpstan analyse packages/affiliates/src --level=6
./vendor/bin/pint packages/affiliates/src/Models/AffiliateLink.php --test

# Package-wide final check
./vendor/bin/phpstan analyse packages/affiliates/src --level=6
./vendor/bin/pint packages/affiliates --test
```

---

## Risk Assessment

| Risk | Level | Mitigation |
|------|-------|------------|
| Missing data during backfill | Low | `NOW()` is acceptable; exact historical time of deactivation not critical |
| Model accessor name changes | Low | No backward compat needed |
| State transition timestamp gaps | Low | Only affects future transitions |
| Index changes affecting query perf | Low-Medium | Review index changes before deploy |

---

## Final Principle Check

After all changes, the package should satisfy:

```
status describes lifecycle.                    ✓ (state machines / BackedEnums)
approved_at describes when approved.           ✓ (existing)
verified_at describes when verified.           ✓ (existing, is_verified removed)
deactivated_at describes when deactivated.     ✓ (NEW for Affiliate.rejected, AffiliateLink)
cancelled_at describes when cancelled.         ✓ (NEW for AffiliatePayout)
archived_at describes when archived.           ✓ (NEW for AffiliateProgram)
completed_at describes when completed.         ✓ (existing in TrainingProgress)
paid_at describes when paid.                   ✓ (existing + NEW in Conversion)
rejected_at describes when rejected.           ✓ (NEW for Conversion, Membership)
paused_at describes when paused.               ✓ (NEW for Affiliate, AffiliateProgram)
suspended_at describes when suspended.         ✓ (NEW for Membership)
failed_at describes when failed.               ✓ (NEW for Payout)
dismissed_at describes when dismissed.         ✓ (NEW for FraudSignal)
confirmed_at describes when confirmed.         ✓ (NEW for FraudSignal)
visibility describes who can access.           ✓ (replaces is_public)
starts_at/ends_at describe scheduled window.   ✓ (existing)
```
