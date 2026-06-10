# CHIP Package — Lifecycle Audit & Refactoring Plan

## 1. Executive Summary

The CHIP package has 10 tables (9 prefixed `chip_` + 1 shared `webhook_calls`) spanning Collect and Send APIs. The audit reveals **three systemic problems**: (a) a dual timestamp system where integer `*_on` API-mirror columns coexist with `timestampTz` Laravel columns, (b) missing `status` column on `chip_payments` (a legitimate payment lifecycle entity), and (c) lifecycle-event booleans (`verified`, `processed`, `marked_as_paid`) that should be `*_at` timestampTz columns.

`chip_clients` and `chip_send_webhooks` are **configuration entities** — they do not need `status` columns or state machines. A single `deactivated_at` timestampTz provides an audit trail without over-engineering.

The `SendInstruction` table uses `state` instead of `status`, creating inconsistency with the rest of the ecosystem.

**Goal**: Every lifecycle table has a `status` column describing its lifecycle. Config tables get `deactivated_at` for audit. All lifecycle events use `*_at timestampTz` columns. The dual integer/timestampTz system is eliminated in favor of `timestampTz` only. No backward compatibility.

---

## 2. Full Inventory by Table

### 2.1 `chip_purchases` — Purchase

| Aspect | Current State | Problem |
|--------|-------------|---------|
| PK | `uuid('id')` | OK |
| Status | `status` (string, 32), backed by `PurchaseStatus` enum — 28 values | Column exists, properly typed |
| Lifecycle timestamps (timestampTz) | `created_at`, `updated_at`, `failed_at`, `refunded_at` | Good |
| Lifecycle timestamps (integer `*_on`) | `created_on`, `updated_on`, `viewed_on`, `due` | Should be timestampTz |
| Lifecycle booleans | `marked_as_paid` (bool) | Should be `marked_paid_at` timestampTz |
| Config flags (OK as bool) | `send_receipt`, `is_test`, `is_recurring_token`, `skip_capture`, `force_recurring` | OK — not lifecycle events |
| Missing timestamps | `completed_at`, `cancelled_at`, `expired_at`, `cleared_at`, `settled_at` | Multiple terminal states lack dedicated `*_at` columns |
| Owner | `nullableMorphs('owner')` | OK |

**Model**: `ChipModel` (UUID, HasOwner, HasUuids, Auditable). `$timestamps = false` on base but Purchase model needs `timestampsTz()`. Model casts `created_at`/`updated_at` as `'datetime'` — should be `'immutable_datetime'`.

---

### 2.2 `chip_payments` — Payment

| Aspect | Current State | Problem |
|--------|-------------|---------|
| PK | `uuid('id')` | OK |
| Status | **MISSING** | No status column. Payment has `payment_type` (purchase/refund/payout) but no status tracking |
| Lifecycle timestamps (timestampTz) | `created_at`, `updated_at` | Good |
| Lifecycle timestamps (integer `*_on`) | `created_on`, `updated_on`, `paid_on`, `remote_paid_on`, `pending_unfreeze_on` | Should be timestampTz |
| Boolean (OK) | `is_outgoing` | Directional flag — OK |

**Model**: `ChipModel` (UUID). Model has `$timestamps = false` in base.

---

### 2.3 `webhook_calls` — Webhook (shared table, extended by CHIP)

| Aspect | Current State | Problem |
|--------|-------------|---------|
| PK | from spatie WebhookCall (bigIncrements) | OK (inherited) |
| Status | `status` (string, default `'pending'`) | OK |
| Lifecycle timestamps (timestampTz) | `created_at`, `updated_at`, `processed_at`, `last_retry_at` | Good |
| Lifecycle timestamps (integer `*_on`) | `created_on`, `updated_on` (nullable) | Should be timestampTz |
| Lifecycle booleans | `verified` (bool), `processed` (bool) | Should be `verified_at`, `processed_at` (latter already has column but boolean also exists) |
| Config flags | `all_events` | OK |
| Missing | `failed_at` | Webhook has `status = 'failed'` but no `failed_at` timestamp |

**Model**: `Webhook` extends `WebhookCall` (spatie). Uses `HasOwner`. Casts `verified`/`processed` as boolean — should become `verified_at`/`failed_at` timestamps. Has `processed_at` cast already.

---

### 2.4 `chip_bank_accounts` — BankAccount

| Aspect | Current State | Problem |
|--------|-------------|---------|
| PK | `integer('id')` | API-driven, OK |
| Status | `status` (string, 16), backed by `BankAccountStatus` enum | OK |
| Lifecycle timestamps (timestampTz) | `created_at`, `updated_at`, `deleted_at` | Good |
| Lifecycle booleans (OK) | `is_debiting_account`, `is_crediting_account` | Capability flags — OK |
| Missing | `verified_at`, `rejected_at` | Status transitions `pending→verified` and `pending→rejected` lack dedicated timestamps |
| Soft delete | `deleted_at` column exists | Model (`ChipIntegerModel`) does **not** use `SoftDeletes` trait — orphan column |

**Model**: `ChipIntegerModel`. `$timestamps = true` set explicitly.

---

### 2.5 `chip_clients` — Client

| Aspect | Current State | Problem |
|--------|-------------|---------|
| PK | `uuid('id')` | OK |
| Status | None | Config entity — no `status` column needed |
| Lifecycle timestamps (timestampTz) | `created_at`, `updated_at` | Good |
| Lifecycle timestamps (integer `*_on`) | `created_on`, `updated_on` | Should be timestampTz |
| Missing | `deactivated_at` | Config toggle audit trail |

**Model**: `ChipModel` (UUID). `$timestamps = true`.

---

### 2.6 `chip_send_instructions` — SendInstruction

| Aspect | Current State | Problem |
|--------|-------------|---------|
| PK | `integer('id')` | API-driven, OK |
| Status | Uses **`state`** (string, 24), backed by `SendInstructionState` enum | Should be `status` for consistency |
| Lifecycle timestamps (timestampTz) | `created_at`, `updated_at` | Good |
| Missing | `completed_at`, `rejected_at`, `deleted_at`, `accepted_at` | Enum has `COMPLETED`, `REJECTED`, `DELETED`, `ACCEPTED` but no transition timestamps |

**Model**: `ChipIntegerModel`. Casts `created_at`/`updated_at` as `'datetime'`.

---

### 2.7 `chip_send_limits` — SendLimit

| Aspect | Current State | Problem |
|--------|-------------|---------|
| PK | `integer('id')` | API-driven, OK |
| Status | `status` (string, 24) | OK |
| Lifecycle timestamps (timestampTz) | `created_at`, `updated_at` | Good |
| Lifecycle date | `from_settlement` (date, nullable) | OK as date type |
| Missing | `approved_at`, `expired_at`, `rejected_at` | Status transitions lack dedicated timestamps |
| Approval tracking | `approvals_required`, `approvals_received` (integer) | OK |

**Model**: `ChipIntegerModel`.

---

### 2.8 `chip_send_webhooks` — SendWebhook

| Aspect | Current State | Problem |
|--------|-------------|---------|
| PK | `integer('id')` | API-driven, OK |
| Status | None | Config entity — no `status` column needed |
| Lifecycle timestamps (timestampTz) | `created_at`, `updated_at` | Good |
| Bug | Model sets `$timestamps = false` | DB has `created_at`/`updated_at` columns — Eloquent won't auto-manage them |
| Missing | `deactivated_at`, `verified_at` | Config toggle + webhook verification tracking |

**Model**: `ChipIntegerModel`. Explicitly `$timestamps = false`.

---

### 2.9 `chip_company_statements` — CompanyStatement

| Aspect | Current State | Problem |
|--------|-------------|---------|
| PK | `uuid('id')` | OK |
| Status | `status` (string, 24) | OK |
| Lifecycle timestamps (timestampTz) | `created_at`, `updated_at` | Good |
| Lifecycle timestamps (integer `*_on`) | `created_on`, `updated_on`, `began_on`, `finished_on` | Should be timestampTz |
| Boolean | `is_test` | OK — mode flag |
| Missing | `completed_at`, `failed_at`, `expired_at` | Terminal states lack dedicated timestamps |

**Model**: `ChipModel` (UUID). `$timestamps = true`.

---

### 2.10 `chip_customers` — ChipCustomerLink

| Aspect | Current State | Problem |
|--------|-------------|---------|
| PK | `uuid('id')` | OK |
| Status | NONE | OK — pure polymorphic link table, no lifecycle |
| Lifecycle timestamps (timestampTz) | `created_at`, `updated_at` | Good |
| Morph columns | `subject_type`, `subject_id`, `owner_type`, `owner_id` | OK |

**Model**: `ChipModel` (UUID). `$timestamps = true`. Cleanest table in the package.

---

## 3. Problems Summary

### 3.1 Critical

| # | Problem | Tables Affected | Severity |
|---|---------|----------------|----------|
| P1 | **Missing `status` column on payment lifecycle entity** | `chip_payments` | High |
| P2 | **Lifecycle booleans instead of `*_at` timestamps** | `webhook_calls`: `verified`→`verified_at`, `processed` (duplicate of `processed_at`); `chip_purchases`: `marked_as_paid`→`marked_paid_at` | High |
| P3 | **`state` instead of `status`** | `chip_send_instructions` | Medium |

### 3.2 Timestamp Inconsistencies

| # | Problem | Tables Affected | Severity |
|---|---------|----------------|----------|
| T1 | **Integer `*_on` columns alongside `timestampTz` `*_at`** | `chip_purchases`, `chip_payments`, `chip_clients`, `webhook_calls`, `chip_company_statements` | High |
| T2 | **Mixed timestamp types in same table** | `chip_purchases`: `failed_at`/`refunded_at` (timestampTz) but `viewed_on`/`due` (integer) | Medium |
| T3 | **No terminal-state timestamps** | `chip_purchases`, `chip_send_instructions`, `chip_send_limits`, `chip_company_statements`, `chip_bank_accounts`, `webhook_calls`, `chip_payments` | Medium |

### 3.3 Model/Column Mismatches

| # | Problem | Detail | Severity |
|---|---------|--------|----------|
| M1 | `chip_bank_accounts` has `deleted_at` column but `ChipIntegerModel` lacks `SoftDeletes` | Orphan column — either add trait or drop column | Medium |
| M2 | `chip_send_webhooks` model sets `$timestamps = false` but DB has `created_at`/`updated_at` | Eloquent won't auto-fill — columns exist but are never touched by framework | Medium |
| M3 | Casts use `'datetime'` instead of `'immutable_datetime'` | Violates CarbonImmutable guideline | Low |
| M4 | Purchase model has `$timestamps = false` in `ChipModel` base | Individual models set `$timestamps = true` — fragile pattern | Low |

### 3.4 Positive Notes

- All tables use `uuid('id')->primary()` or `integer('id')->primary()` consistently — no mixed PK types within a table.
- All migrations use `nullableMorphs('owner')` — correct multitenancy pattern.
- `chip_customers` is clean — no problems.
- Status enums are comprehensive and well-documented with source references.
- `PaymentMethod` and modal enums (`FpxBank`, `FpxType`, `EWallet`) are reference data — not lifecycle concerns.

---

## 4. Recommended Structure

### 4.1 Standard Column Set for Lifecycle Tables

```sql
status      VARCHAR(32) NOT NULL DEFAULT '...'
created_at  TIMESTAMPTZ NOT NULL
updated_at  TIMESTAMPTZ NOT NULL
<terminal_state>_at  TIMESTAMPTZ NULL  -- one per business-critical terminal/failure state
```

### 4.2 Per-Table Target Schema

#### `chip_purchases`

| Column | Type | Notes |
|--------|------|-------|
| `status` | string(32) | Keep. `PurchaseStatus` enum. |
| `created_at` | timestampTz | Keep. |
| `updated_at` | timestampTz | Keep. |
| `viewed_at` | timestampTz | Rename from `viewed_on`. |
| `due_at` | timestampTz | Rename from `due`. |
| `failed_at` | timestampTz | Keep. |
| `refunded_at` | timestampTz | Keep. |
| `marked_paid_at` | timestampTz | Convert from `marked_as_paid` boolean. |
| `completed_at` | timestampTz | **New**. TRIGGER: status → paid/cleared/settled. |
| `cancelled_at` | timestampTz | **New**. TRIGGER: status → cancelled. |
| `expired_at` | timestampTz | **New**. TRIGGER: status → expired. |
| `sent_at` | timestampTz | **New**. TRIGGER: status → sent. |
| `cleared_at` | timestampTz | **New**. TRIGGER: status → cleared. |
| `settled_at` | timestampTz | **New**. TRIGGER: status → settled. |
| **DROP** | `created_on`, `updated_on`, `viewed_on`, `due`, `marked_as_paid` | Replaced by above. |
| KEEP | `send_receipt`, `is_test`, `is_recurring_token`, `recurring_token`, `skip_capture`, `force_recurring` | Config flags. |

#### `chip_payments`

| Column | Type | Notes |
|--------|------|-------|
| `status` | string(32) | **New**. Values: pending, processing, paid, failed, refunded, cancelled. |
| `created_at` | timestampTz | Keep. |
| `updated_at` | timestampTz | Keep. |
| `paid_at` | timestampTz | Rename from `paid_on`. |
| `remote_paid_at` | timestampTz | Rename from `remote_paid_on`. |
| `pending_unfreeze_at` | timestampTz | Rename from `pending_unfreeze_on`. |
| `failed_at` | timestampTz | **New**. |
| `refunded_at` | timestampTz | **New**. |
| **DROP** | `created_on`, `updated_on`, `paid_on`, `remote_paid_on`, `pending_unfreeze_on` | Replaced. |
| KEEP | `is_outgoing`, `payment_type`, amount/currency fields | OK. |

#### `webhook_calls`

| Column | Type | Notes |
|--------|------|-------|
| `status` | string(32) | Keep. Values: pending, received, verified, processing, processed, failed. |
| `verified_at` | timestampTz | Convert from `verified` boolean. |
| `failed_at` | timestampTz | **New**. |
| `processed_at` | timestampTz | Already exists in casts, ensure migration has it. |
| **DROP** | `verified`, `processed` (boolean), `created_on`, `updated_on` | Replaced. |

> Note: `webhook_calls` is a **shared table** owned by `commerce-support`. Coordinate changes there.

#### `chip_bank_accounts`

| Column | Type | Notes |
|--------|------|-------|
| `status` | string(16) | Keep. `BankAccountStatus` enum. |
| `created_at` | timestampTz | Keep. |
| `updated_at` | timestampTz | Keep. |
| `verified_at` | timestampTz | **New**. TRIGGER: status → verified. |
| `rejected_at` | timestampTz | **New**. TRIGGER: status → rejected. |
| `deleted_at` | timestampTz | Keep (add `SoftDeletes` to model or drop column). |
| KEEP | `is_debiting_account`, `is_crediting_account` | Capability flags. |

#### `chip_clients` — Config Entity

| Column | Type | Notes |
|--------|------|-------|
| `created_at` | timestampTz | Keep. |
| `updated_at` | timestampTz | Keep. |
| `deactivated_at` | timestampTz | **New**. Config toggle audit trail. No `status` column needed. |
| **DROP** | `created_on`, `updated_on` | Replaced. |

#### `chip_send_instructions`

| Column | Type | Notes |
|--------|------|-------|
| `status` | string(24) | Rename from `state`. `SendInstructionState` enum. |
| `created_at` | timestampTz | Keep. |
| `updated_at` | timestampTz | Keep. |
| `completed_at` | timestampTz | **New**. TRIGGER: status → completed. |
| `rejected_at` | timestampTz | **New**. TRIGGER: status → rejected. |
| `deleted_at` | timestampTz | **New**. TRIGGER: status → deleted. |
| `accepted_at` | timestampTz | **New**. TRIGGER: status → accepted. |

#### `chip_send_limits`

| Column | Type | Notes |
|--------|------|-------|
| `status` | string(24) | Keep. |
| `created_at` | timestampTz | Keep. |
| `updated_at` | timestampTz | Keep. |
| `approved_at` | timestampTz | **New**. TRIGGER: status → approved. |
| `expired_at` | timestampTz | **New**. Overload: also used as lifecycle timestamp. |
| `rejected_at` | timestampTz | **New**. TRIGGER: status → rejected. |
| KEEP | `from_settlement`, `approvals_required`, `approvals_received` | OK. |

#### `chip_send_webhooks` — Config Entity

| Column | Type | Notes |
|--------|------|-------|
| `created_at` | timestampTz | Keep (fix model: set `$timestamps = true`). |
| `updated_at` | timestampTz | Keep (fix model). |
| `deactivated_at` | timestampTz | **New**. Config toggle audit trail. |
| `verified_at` | timestampTz | **New**. Webhook URL verification tracking. |
| _No `status` column_ | — | Config entity — no lifecycle state machine needed. |

#### `chip_company_statements`

| Column | Type | Notes |
|--------|------|-------|
| `status` | string(24) | Keep. |
| `created_at` | timestampTz | Keep. |
| `updated_at` | timestampTz | Keep. |
| `began_at` | timestampTz | Rename from `began_on`. |
| `finished_at` | timestampTz | Rename from `finished_on`. |
| `completed_at` | timestampTz | **New**. TRIGGER: status → completed/ready. |
| `failed_at` | timestampTz | **New**. TRIGGER: status → failed. |
| `expired_at` | timestampTz | **New**. TRIGGER: status → expired. |
| **DROP** | `created_on`, `updated_on`, `began_on`, `finished_on` | Replaced. |

#### `chip_customers`

| Column | Type | Notes |
|--------|------|-------|
| All columns | — | No changes needed. Clean table. |

---

## 5. Refactoring Plan — Parallel-Agent Checklist

This plan is structured as independent workstreams that can run in parallel after Step 1.

### Step 0: Prerequisites (sequential)

- [x] Read this entire `lifecycle.md` document.
- [x] Run `php artisan migrate:status` to understand current migration state.
- [x] Check which tables have data (production safety check): `SELECT tablename, n_live_tup FROM pg_stat_user_tables WHERE tablename LIKE 'chip_%' OR tablename = 'webhook_calls';`

### Step 1: Base Model Fixes (sequential, before all parallel tasks)

**Agent A — ChipModel / ChipIntegerModel base classes:**
- [x] Change `$timestamps = false` to `$timestamps = true` on `ChipModel`.
- [x] Change `$timestamps = false` to `$timestamps = true` on `ChipIntegerModel`.
- [x] Remove `$timestamps = true` from individual models that override it (Purchase, Payment, Client, CompanyStatement).
- [x] Change all `'datetime'` casts to `'immutable_datetime'` in all models.
- [x] Add `SoftDeletes` trait to `BankAccount` (or drop `deleted_at` from migration — **decide**).
- [x] Fix `SendWebhook`: remove `$timestamps = false`.

### Step 2: Parallel Table Refactors

Each agent handles ONE table end-to-end: migration, model, enum (if needed), and tests.

#### Agent B — `chip_purchases`
- [x] **Migration**: Drop `created_on`, `updated_on`, `viewed_on`, `due`, `marked_as_paid`. Add `viewed_at`, `due_at`, `marked_paid_at`, `completed_at`, `cancelled_at`, `expired_at`, `sent_at`, `cleared_at`, `settled_at` (all timestampTz nullable).
- [x] **Model**: Update `casts()`. Update `createdOn()` / `updatedOn()` / `viewedOn()` / `dueOn()` accessors to read new columns. Add `markedPaidAt()`, `completedAt()`, `cancelledAt()`, `expiredAt()`, `sentAt()`, `clearedAt()`, `settledAt()` accessors.
- [x] **Model**: Add `status` transition logic in a `setStatusAttribute()` or observer that auto-sets the corresponding `*_at` column.
- [x] **Tests**: Update all Purchase-related tests.

#### Agent C — `chip_payments`
- [x] **Migration**: Add `status` column (string, 32, default `'pending'`). Drop `created_on`, `updated_on`, `paid_on`, `remote_paid_on`, `pending_unfreeze_on`. Add `paid_at`, `remote_paid_at`, `pending_unfreeze_at`, `failed_at`, `refunded_at` (all timestampTz nullable).
- [x] **Model**: Add `PaymentStatus` enum or use string constants. Update `casts()`. Update `paidOn()` / `remotePaidOn()` / `createdOn()` / `updatedOn()` / `pendingUnfreezeOn()` accessors to read new columns.
- [x] **Tests**: Update all Payment-related tests.

#### Agent D — `webhook_calls` (coordinate with commerce-support)
- [x] **commerce-support migration**: Drop `verified`, `processed` booleans. Drop `created_on`, `updated_on`. Add `verified_at`, `failed_at` (timestampTz nullable). Ensure `processed_at` column exists in base migration.
- [x] **Model (chip)**: Update `Webhook` casts. Remove `verified`/`processed` boolean casts. Add `verifiedAt()`, `failedAt()` accessors. Update `markProcessed()` to not touch boolean `processed`.
- [x] **Model (chip)**: Update `status` transition logic.
- [x] **Tests**: Update webhook tests.

#### Agent E — `chip_bank_accounts`
- [x] **Migration**: Add `verified_at`, `rejected_at` (timestampTz nullable).
- [x] **Model**: Add `SoftDeletes` trait. Add `verifiedAt()`, `rejectedAt()` accessors. Add status observer for auto-timestamp.
- [x] **Tests**: Update BankAccount tests.

#### Agent F — `chip_clients` (Config Entity)
- [x] **Migration**: Drop `created_on`, `updated_on`. Add `deactivated_at` (timestampTz nullable). No `status` column.
- [x] **Model**: Update `createdOn()` / `updatedOn()` accessors to use `created_at` / `updated_at`. Add `deactivatedAt()` accessor.
- [x] **Tests**: Update Client tests.

#### Agent G — `chip_send_instructions`
- [x] **Migration**: Rename `state` to `status`. Add `completed_at`, `rejected_at`, `deleted_at`, `accepted_at` (all timestampTz nullable).
- [x] **Model**: Update `stateLabel()` → `statusLabel()`. Update `stateColor()` → `statusColor()`. Add lifecycle timestamp accessors. Add status observer.
- [x] **Grep entire repo** for `->state` references on SendInstruction and update.
- [x] **Tests**: Update SendInstruction tests.

#### Agent H — `chip_send_limits`
- [x] **Migration**: Add `approved_at`, `rejected_at` (timestampTz nullable). Note: `expired_at` likely already maps to the expiry concept; confirm if separate timestamps needed.
- [x] **Model**: Add `approvedAt()`, `rejectedAt()` accessors. Add status observer.
- [x] **Tests**: Update SendLimit tests.

#### Agent I — `chip_send_webhooks` (Config Entity)
- [x] **Migration**: Add `deactivated_at`, `verified_at` (timestampTz nullable). No `status` column.
- [x] **Model**: Remove `$timestamps = false`. Add `deactivatedAt()`, `verifiedAt()` accessors.
- [x] **Tests**: Update SendWebhook tests.

#### Agent J — `chip_company_statements`
- [x] **Migration**: Drop `created_on`, `updated_on`, `began_on`, `finished_on`. Add `began_at`, `finished_at`, `completed_at`, `failed_at`, `expired_at` (all timestampTz nullable).
- [x] **Model**: Update `createdOn()` / `updatedOn()` / `beganOn()` / `finishedOn()` accessors. Add `completedAt()`, `failedAt()`, `expiredAt()` accessors.
- [x] **Tests**: Update CompanyStatement tests.

#### Agent K — Cross-cutting: grep for `*_on` references
- [x] Grep all `packages/*/src` and `app/` for any code referencing the dropped `*_on` columns (e.g. `->created_on`, `->paid_on`, `['created_on']`, `->viewed_on`, `->due`).
- [x] Grep for `->state` on SendInstruction model instances.
- [x] Grep for `marked_as_paid`, `->verified` (on webhook, not other models), `->processed` (on webhook).
- [x] Update all references.

### Step 3: Integration & Verification (sequential, after ALL parallel tasks complete)

- [x] Run all CHIP migrations fresh: `php artisan migrate:fresh` (or in test DB).
- [x] Run PHPStan: `./vendor/bin/phpstan analyse packages/chip/src --level=6`
- [x] Run Pint: `./vendor/bin/pint packages/chip/src packages/chip/database`
- [x] Run tests: `./vendor/bin/pest --parallel packages/chip/tests`
- [x] Run cross-package tests (filament-chip, cashier-chip, checkout): `./vendor/bin/pest --parallel packages/filament-chip/tests packages/cashier-chip/tests`
- [x] Verify no remaining `*_on` integer columns: check schema with `php artisan tinker --execute 'collect(Schema::getColumnListing("chip_purchases"))->filter(fn($c) => str_ends_with($c, "_on"))'`

---

## 6. Migration Strategy

### Principles
- **No backward compatibility**. Fresh installs only or manual migration for existing data.
- **One migration per table** that drops old columns and adds new ones in a single `Schema::table()` call.
- **Data migration**: If existing data must be preserved, write a separate data-migration command (not a DB migration) that reads old columns, converts values, and writes new columns before the schema migration runs.

### Migration File Naming

Use sequential timestamps after the last existing migration (`2026_05_28_000001`):

```
2026_06_10_000001_refactor_chip_purchases_lifecycle.php
2026_06_10_000002_refactor_chip_payments_lifecycle.php
2026_06_10_000003_refactor_webhook_calls_lifecycle.php       (in commerce-support)
2026_06_10_000004_refactor_chip_bank_accounts_lifecycle.php
2026_06_10_000005_refactor_chip_clients_lifecycle.php
2026_06_10_000006_refactor_chip_send_instructions_lifecycle.php
2026_06_10_000007_refactor_chip_send_limits_lifecycle.php
2026_06_10_000008_refactor_chip_send_webhooks_lifecycle.php
2026_06_10_000009_refactor_chip_company_statements_lifecycle.php
```

### Migration Template

```php
Schema::table($tablePrefix . 'purchases', function (Blueprint $table): void {
    // Drop integer *_on columns
    $table->dropColumn(['created_on', 'updated_on', 'viewed_on', 'due', 'marked_as_paid']);

    // Add timestampTz replacements
    $table->timestampTz('viewed_at')->nullable()->after('status');
    $table->timestampTz('due_at')->nullable()->after('viewed_at');
    $table->timestampTz('marked_paid_at')->nullable()->after('due_at');
    $table->timestampTz('completed_at')->nullable()->after('marked_paid_at');
    $table->timestampTz('cancelled_at')->nullable()->after('completed_at');
    $table->timestampTz('expired_at')->nullable()->after('cancelled_at');
    $table->timestampTz('sent_at')->nullable()->after('expired_at');
    $table->timestampTz('cleared_at')->nullable()->after('sent_at');
    $table->timestampTz('settled_at')->nullable()->after('cleared_at');
});
```

### Model Accessor Migration Pattern

Before (integer `*_on`):
```php
public function createdOn(): Attribute
{
    return Attribute::get(fn (?int $value, array $attributes): ?CarbonImmutable =>
        $this->toTimestamp($attributes['created_on'] ?? null));
}
```

After (timestampTz `*_at`):
```php
public function createdAt(): Attribute
{
    return Attribute::get(fn (?CarbonImmutable $value): ?CarbonImmutable => $value);
}
```

### Status Observer Pattern

Each lifecycle model should auto-set transition timestamps when status changes:

```php
protected static function booted(): void
{
    static::updating(function (self $model): void {
        if ($model->isDirty('status')) {
            $newStatus = $model->status;

            if ($newStatus === 'completed' && $model->completed_at === null) {
                $model->completed_at = now();
            }
            if ($newStatus === 'cancelled' && $model->cancelled_at === null) {
                $model->cancelled_at = now();
            }
            // ... per status
        }
    });
}
```

---

## 7. Verification Commands

### Schema Verification

```bash
# Verify no integer *_on columns remain in any chip_ table
php artisan tinker --execute '
$tables = ["chip_purchases","chip_payments","chip_clients","chip_bank_accounts",
           "chip_send_instructions","chip_send_limits","chip_send_webhooks",
           "chip_company_statements","chip_customers"];
foreach ($tables as $t) {
    $cols = Schema::getColumnListing($t);
    $bad = array_filter($cols, fn($c) => str_ends_with($c, "_on") || $c === "state" || $c === "verified" || $c === "processed" || $c === "marked_as_paid");
    if ($bad) echo "$t: " . implode(", ", $bad) . PHP_EOL;
}
'

# Verify lifecycle tables have status column, config tables have deactivated_at
php artisan tinker --execute '
echo "chip_purchases: " . (Schema::hasColumn("chip_purchases", "status") ? "OK" : "MISSING") . PHP_EOL;
echo "chip_payments: " . (Schema::hasColumn("chip_payments", "status") ? "OK" : "MISSING") . PHP_EOL;
echo "chip_clients: " . (Schema::hasColumn("chip_clients", "deactivated_at") ? "OK" : "MISSING") . PHP_EOL;
echo "chip_send_webhooks: " . (Schema::hasColumn("chip_send_webhooks", "deactivated_at") ? "OK" : "MISSING") . PHP_EOL;
'
```

### Codebase Grep Verification

```bash
# Check for references to dropped columns
rg -n "created_on|updated_on|paid_on|remote_paid_on|viewed_on|pending_unfreeze_on|began_on|finished_on" packages/chip/src packages/filament-chip/src packages/cashier-chip/src

# Check for ->state on SendInstruction (should be ->status after refactor)
rg -n "\->state\b" packages/chip/src/Models/SendInstruction.php packages/filament-chip/src

# Check for boolean lifecycle flags that should be timestamps
rg -n "marked_as_paid|->verified|->processed" packages/chip/src packages/filament-chip/src

# Verify no constrained/cascadeOnDelete in migrations
rg -n "constrained\(|cascadeOnDelete\(" packages/chip/database
```

### Static Analysis & Tests

```bash
# PHPStan at level 6, chip package only
./vendor/bin/phpstan analyse packages/chip/src --level=6

# Format changed files
./vendor/bin/pint packages/chip/src packages/chip/database

# Run chip tests in parallel
./vendor/bin/pest --parallel packages/chip/tests

# Run dependent packages
./vendor/bin/pest --parallel packages/filament-chip/tests packages/cashier-chip/tests

# Cross-package grep for SendInstruction state references
rg -n "send_instruction" packages/*/src --include="*.php" -l | xargs rg -n "->state"
```

### Final Checklist

- [x] `git diff --stat` shows only expected files changed
- [x] No `*_on` integer columns in any migration schema
- [x] `chip_payments` has a `status` column; `chip_clients` and `chip_send_webhooks` have `deactivated_at`
- [x] No `verified`, `processed`, or `marked_as_paid` boolean columns remain
- [x] `SendInstruction` uses `status` not `state`
- [x] All models use `'immutable_datetime'` casts
- [x] `SendWebhook` model has `$timestamps = true` (or removed override)
- [x] `BankAccount` has `SoftDeletes` trait (or `deleted_at` column dropped)
- [x] PHPStan passes at level 6
- [x] All chip tests pass with `--parallel`
- [x] Dependent package tests pass
