# Cashier-Chip Lifecycle Audit & Refactoring Plan

## 1. Executive Summary

Cashier-Chip manages three persisted Eloquent models backed by three DB tables. The package uses a
**string `chip_status` column** on `Subscription` to drive lifecycle transitions — this is the
correct pattern (`status` drives lifecycle). However, the model layer uses `ends_at` ambiguously
for both cancellation request and actual expiration, and **omits several business-critical
lifecycle timestamps** that the existing business logic already implies (`paused_at`, `past_due_at`,
`canceled_at`, `trial_started_at`, `renewed_at`). The refactoring plan adds these timestamps and
splits `ends_at` semantics. `is_default` on payment methods is a config designation — keep as
boolean. Visibility columns are not needed on billing entities.

No backward compatibility is required — the migration is breaking by design.

---

## 2. Full Inventory by Table

### 2.1 `cashier_chip_subscriptions`

Source: `database/migrations/2000_03_01_000002_create_chip_subscriptions_table.php`
Model: `src/Subscription/Subscription.php`

| Column | Type | Current Role | Problem |
|--------|------|-------------|---------|
| `id` | `uuid` | PK | OK |
| `owner_type` | `nullableMorphs` | Tenant boundary | OK |
| `owner_id` | `nullableMorphs` | Tenant boundary | OK |
| `billable_type` | `uuidMorphs` | Billable subject | OK |
| `billable_id` | `uuidMorphs` | Billable subject | OK |
| `type` | `string` | Subscription plan identifier | OK |
| `chip_id` | `string` unique | External CHIP reference | OK |
| `chip_status` | `string` | **Lifecycle status** | Correct pattern (status drives lifecycle) |
| `chip_price` | `string` nullable | Single-price reference | OK |
| `quantity` | `integer` nullable | Item count for single-price subs | OK |
| `recurring_token` | `string` nullable | Payment token reference | OK |
| `billing_interval` | `string` default month | Interval unit | OK |
| `billing_interval_count` | `integer` default 1 | Interval multiplier | OK |
| `trial_ends_at` | `timestampTz` nullable | Trial expiration | Present |
| `next_billing_at` | `timestampTz` nullable | Next billing date | Present |
| `ends_at` | `timestampTz` nullable | **AMBIGUOUS** — means both "canceled" AND "ended" | Split into `canceled_at` + `ends_at` |
| `coupon_id` | `string` nullable | Applied coupon code | OK |
| `coupon_discount` | `integer` nullable | Discount amount | OK |
| `coupon_duration` | `string` nullable | once/repeating/forever | OK |
| `coupon_applied_at` | `timestampTz` nullable | When coupon was applied | Present |
| `created_at` | `timestampTz` | | OK |
| `updated_at` | `timestampTz` | | OK |

**Missing lifecycle timestamps:**

| Missing Column | Evidence from Code |
|---|---|
| `canceled_at` | `Subscription::cancel()` sets `ends_at`; should be two distinct moments: *cancellation requested* vs *subscription actually ends* |
| `paused_at` | `Subscription::pause()` exists at line 1106 but no timestamp recorded |
| `past_due_at` | `Subscription::pastDue()` → `chip_status = 'past_due'`; `RenewSubscriptionsCommand::markAsPastDue()` at line 186 |
| `trial_started_at` | `onTrial()` at line 393; only `trial_ends_at` exists — cannot compute trial duration |
| `renewed_at` | `RenewSubscriptionsCommand` at line 112 updates `next_billing_at` but never records *when* the last renewal succeeded |

### 2.2 `cashier_chip_subscription_items`

Source: `database/migrations/2000_03_01_000003_create_chip_subscription_items_table.php`
Model: `src/Subscription/SubscriptionItem.php`

| Column | Type | Current Role | Problem |
|--------|------|-------------|---------|
| `id` | `uuid` | PK | OK |
| `owner_type` | `nullableMorphs` | Tenant boundary | OK |
| `owner_id` | `nullableMorphs` | Tenant boundary | OK |
| `subscription_id` | `foreignUuid` | Parent subscription FK | OK |
| `chip_id` | `string` unique | External CHIP line-item reference | OK |
| `chip_product` | `string` nullable | Product identifier | OK |
| `chip_price` | `string` nullable | Price identifier | OK |
| `quantity` | `integer` nullable | Item quantity | OK |
| `unit_amount` | `integer` nullable | Price per unit in minor units | OK |
| `created_at` | `timestampTz` | | OK |
| `updated_at` | `timestampTz` | | OK |

**No changes needed.** Items have no independent lifecycle — they inherit from the parent
Subscription. Items are created and destroyed atomically with swap/remove operations.

### 2.3 `cashier_chip_payment_methods`

Source: `database/migrations/2000_03_01_000004_create_chip_payment_methods_table.php`
Model: `src/Payment/StoredPaymentMethod.php`

| Column | Type | Current Role | Problem |
|--------|------|-------------|---------|
| `id` | `uuid` | PK | OK |
| `owner_type` | `nullableMorphs` | Tenant boundary | OK |
| `owner_id` | `nullableMorphs` | Tenant boundary | OK |
| `billable_type` | `uuidMorphs` | Billable subject | OK |
| `billable_id` | `uuidMorphs` | Billable subject | OK |
| `recurring_token` | `string` | CHIP recurring token ID | OK |
| `type` | `string` nullable | card/bank/etc. | OK |
| `brand` | `string` nullable | Card brand | OK |
| `last_four` | `string(4)` nullable | Last 4 digits | OK |
| `is_default` | `boolean` default false | Config designation | OK — config designation, keep boolean |
| `metadata` | `jsonb` nullable | Extra token data | OK |
| `created_at` | `timestampTz` | | OK |
| `updated_at` | `timestampTz` | | OK |

**No changes needed.** `is_default` is a config designation, not a lifecycle event. Payment
methods do not have an independent lifecycle.

---

## 3. Problems Summary

### P1 (Critical — breaks lifecycle contract)

1. **`ends_at` ambiguity on `subscriptions`**: One column serves two concepts: "when did the user
   cancel?" and "when does the subscription actually end?". `cancel()` sets
   `ends_at = trial_ends_at || next_billing_at` (grace period end), while `cancelNow()` /
   `markAsCanceled()` sets `ends_at = Carbon::now()` (immediate end). These are different
   lifecycle events and need separate columns.

### P2 (Missing business-critical lifecycle timestamps)

2. **`subscriptions.canceled_at`** — Separate from `ends_at` (see P1)
3. **`subscriptions.paused_at`** — `pause()` / `unpause()` exist with no timestamp
4. **`subscriptions.past_due_at`** — Past-due state set by multiple code paths with no timestamp
5. **`subscriptions.trial_started_at`** — Only `trial_ends_at` exists; cannot compute trial length
6. **`subscriptions.renewed_at`** — Renewal command updates `next_billing_at` but never stamps
   when renewal happened

---

## 4. Recommended Structure

### 4.1 `cashier_chip_subscriptions` — Target Schema

```sql
CREATE TABLE cashier_chip_subscriptions (
    id                      uuid PRIMARY KEY,
    owner_type              varchar,
    owner_id                uuid,
    billable_type           varchar NOT NULL,
    billable_id             uuid NOT NULL,
    type                    varchar NOT NULL,
    chip_id                 varchar NOT NULL UNIQUE,
    chip_status             varchar NOT NULL,           -- lifecycle status
    chip_price              varchar,
    quantity                integer,
    recurring_token         varchar,
    billing_interval        varchar NOT NULL DEFAULT 'month',
    billing_interval_count  integer NOT NULL DEFAULT 1,
    trial_started_at        timestamp with time zone,   -- NEW
    trial_ends_at           timestamp with time zone,
    renewed_at              timestamp with time zone,   -- NEW (last successful renewal)
    next_billing_at         timestamp with time zone,
    canceled_at             timestamp with time zone,   -- NEW (when cancellation requested)
    ends_at                 timestamp with time zone,   -- CHANGED: now only means actual end/expiration
    paused_at               timestamp with time zone,   -- NEW
    past_due_at             timestamp with time zone,   -- NEW
    coupon_id               varchar,
    coupon_discount         integer,
    coupon_duration         varchar,
    coupon_applied_at       timestamp with time zone,
    created_at              timestamp with time zone NOT NULL,
    updated_at              timestamp with time zone NOT NULL
);
```

**Lifecycle status transitions (chip_status):**

```
                     ┌──────────┐
                     │ inactive │ (initial / post-expiration)
                     └────┬─────┘
                          │ newSubscription()
                          ▼
              ┌───────────────────────┐
              │      trialing         │ trial_started_at = now, trial_ends_at = date
              └───────────┬───────────┘
                    │           │
          trial ends│           │ cancel() during trial
                    ▼           ▼
              ┌──────────┐  ┌──────────┐
              │  active   │  │ canceled │ canceled_at = now, ends_at = trial_ends_at
              └─────┬─────┘  └──────────┘
                    │
        ┌───────────┼───────────┐
        │           │           │
        ▼           ▼           ▼
   ┌─────────┐ ┌────────┐ ┌──────────┐
   │ paused  │ │past_due│ │ canceled │ cancel() → canceled_at = now, ends_at = next_billing_at
   └────┬────┘ └───┬────┘ └────┬─────┘
        │          │           │
   unpause()   payment   ends_at reached
        │      succeeds       │
        ▼          ▼          ▼
   ┌──────────┐ ┌──────────┐ ┌─────────┐
   │  active   │ │  active  │ │ expired │ ends_at < now, chip_status → canceled
   └──────────┘ └──────────┘ └─────────┘
```

### 4.2 `cashier_chip_subscription_items` — Target Schema

No changes. Items inherit lifecycle from the parent subscription.

### 4.3 `cashier_chip_payment_methods` — Target Schema

No changes. `is_default` boolean is appropriate as a config designation. Payment methods have no
independent lifecycle.

---

## 5. Refactoring Plan — Parallel-Agent Checklist

### Phase A: Add new migration
- [x] Create `2000_04_01_000000_refactor_subscription_lifecycle.php`
  - Add columns: `trial_started_at`, `renewed_at`, `canceled_at`, `paused_at`, `past_due_at`
  - Backfill `canceled_at` from `ends_at` where `chip_status = 'canceled'` and `ends_at IS NOT NULL`

### Phase B: Update Subscription model (parallel with C)
- [x] Add casts: `trial_started_at`, `renewed_at`, `canceled_at`, `paused_at`, `past_due_at`
- [x] Replace all `ends_at` writes for cancellation with `canceled_at` + `ends_at`:
  - `cancel()` / `cancelAt()` → set `canceled_at = now()`, keep `ends_at = trial_ends_at || next_billing_at`
  - `cancelNow()` / `markAsCanceled()` → set `canceled_at = now()`, `ends_at = now()`
  - `resume()` → set `canceled_at = null`, `ends_at = null`
- [x] Update `pause()` → set `paused_at = now()`
- [x] Update `unpause()` → clear `paused_at`
- [x] Update `syncChipStatus()` / `calculateCurrentStatus()` to set `past_due_at` when
  transitioning to past_due
- [x] Update `endTrial()` to set `trial_started_at` when starting trial (if not already set)
- [x] Update `canceled()` check: `! is_null($this->canceled_at)` instead of `! is_null($this->ends_at)`
- [x] Update `ended()` check: `$this->canceled_at && ! $this->onGracePeriod()`
- [x] Update `onGracePeriod()`: `$this->ends_at && $this->ends_at->isFuture()`
- [x] Update all scopes using `ends_at` to also check `canceled_at` where semantically correct
- [x] Update PHPDoc `@property` annotations

### Phase C: Update Concerns & Actions (parallel with B)
- [x] `CreateChipSubscription` → set `trial_started_at = now()` when trial is active
- [x] `CancelChipSubscription` → set `canceled_at = now()` alongside `ends_at`
- [x] `RenewSubscriptionsCommand` → set `renewed_at = now()` on successful renewal
- [x] `RenewSubscriptionsCommand::markAsPastDue()` → set `past_due_at = now()`

### Phase D: Update factory classes
- [x] `SubscriptionFactory` → add states: `trialStartedAt()`, `renewedAt()`, `canceledAt()`,
  `pausedAt()`, `pastDueAt()`
- [x] `SubscriptionFactory` → update `canceled()` to set `canceled_at` alongside `ends_at`

### Phase E: Update tests (parallel across test files)
- [x] Update `CancelChipSubscriptionTest` → assert `canceled_at` is set
- [x] Update `CreateChipSubscriptionTest` → assert `trial_started_at` when trial active
- [x] Add lifecycle transition tests covering pause/resume/cancel/resume flow

---

## 6. Migration Strategy

### Approach: Single migration per table

Since the monorepo is in beta with no backward compatibility requirement, use **one-shot
migrations** that add columns:

```php
// 2000_04_01_000000_refactor_subscription_lifecycle.php
public function up(): void
{
    Schema::table($subscriptionsTable, function (Blueprint $table): void {
        $table->timestampTz('trial_started_at')->nullable()->after('billing_interval_count');
        $table->timestampTz('renewed_at')->nullable()->after('next_billing_at');
        $table->timestampTz('canceled_at')->nullable()->after('next_billing_at');
        $table->timestampTz('paused_at')->nullable()->after('ends_at');
        $table->timestampTz('past_due_at')->nullable()->after('paused_at');
    });

    // Backfill canceled_at from ends_at where chip_status = 'canceled'
    DB::table($subscriptionsTable)
        ->where('chip_status', 'canceled')
        ->whereNotNull('ends_at')
        ->update(['canceled_at' => DB::raw('ends_at')]);
}
```

### Rollout order
1. Run migrations (Phase A)
2. Deploy model/concern/action changes (Phases B–D)
3. Run tests (Phase E)
4. Deploy together (no backward compat window needed)

---

## 7. Verification Commands

```bash
# 1. Run migrations fresh
php artisan migrate:fresh --path=packages/cashier-chip/database/migrations

# 2. Verify schema (all new columns present)
php artisan db:show | grep cashier_chip

# 3. Run cashier-chip test suite with parallel
./vendor/bin/pest --parallel packages/cashier-chip/tests

# 4. Grep for all uses of ends_at in subscription logic (should be only grace-period checks)
rg -n -- "ends_at" packages/cashier-chip/src/Subscription/Subscription.php

# 5. Verify all new casts exist
rg -n -- "canceled_at|paused_at|past_due_at|trial_started_at|renewed_at" packages/cashier-chip/src

# 6. PHPStan on the package
./vendor/bin/phpstan analyse packages/cashier-chip/src --level=6

# 7. Verify no constrained() / cascadeOnDelete() in migrations
rg -n -- "constrained\(|cascadeOnDelete\(" packages/cashier-chip/database

# 8. Verify is_default still boolean on payment methods
rg -n -- "is_default" packages/cashier-chip/database/migrations

# 9. Cross-package test (filament-cashier-chip if installed)
./vendor/bin/pest --parallel packages/filament-cashier-chip/tests 2>/dev/null || echo "filament-cashier-chip not present — OK"
```
