# Checkout — Lifecycle Field Audit

## Executive Summary

The `checkout_sessions` table uses a Spatie ModelStates `status` column as its lifecycle driver, which is correct. However, only two transition timestamps exist (`completed_at`, `expires_at`), and `expires_at` records the *scheduled* expiry deadline, not the *actual* transition time into the `Expired` state. No `cancelled_at` or `payment_failed_at` timestamps exist, leaving critical lifecycle transitions untraceable. No `is_*` boolean anti-patterns were found. The package needs 2 new `*_at` timestampTz columns for business-critical terminal transitions, plus wiring in `transitionStatus()` and model `booted()` events.

---

## Full Inventory by Table

### Table: `checkout_sessions`

| # | Column | Type | Nullable | Default | Lifecycle Role | Status |
|---|--------|------|----------|---------|----------------|--------|
| 1 | `id` | uuid | no | — | PK | ok |
| 2 | `cart_id` | string | no | — | Reference | ok |
| 3 | `customer_id` | foreignUuid | yes | — | Reference | ok |
| 4 | `billable_type` | morphs | yes | — | Reference | ok |
| 5 | `billable_id` | morphs | yes | — | Reference | ok |
| 6 | `order_id` | foreignUuid | yes | — | Reference | ok |
| 7 | `payment_id` | string | yes | — | Reference | ok |
| 8 | `owner_type` | morphs | yes | — | Multi-tenancy | ok |
| 9 | `owner_id` | morphs | yes | — | Multi-tenancy | ok |
| 10 | `status` | string | no | `pending` | **Lifecycle driver** — Spatie ModelStates `CheckoutState` | ok |
| 11 | `current_step` | string | yes | — | Step tracker within lifecycle flow | ok |
| 12 | `error_message` | string | yes | — | Context for failed states | ok |
| 13 | `cart_snapshot` | json | yes | — | Data payload | ok |
| 14 | `step_states` | json | yes | — | Per-step status map (StepStatus enum values) | ok |
| 15 | `shipping_data` | json | yes | — | Data payload | ok |
| 16 | `billing_data` | json | yes | — | Data payload | ok |
| 17 | `pricing_data` | json | yes | — | Data payload | ok |
| 18 | `discount_data` | json | yes | — | Data payload | ok |
| 19 | `tax_data` | json | yes | — | Data payload | ok |
| 20 | `payment_data` | json | yes | — | Data payload | ok |
| 21 | `payment_redirect_url` | string(2048) | yes | — | Operational | ok |
| 22 | `payment_attempts` | unsignedSmallInt | no | `0` | Retry counter | ok |
| 23 | `selected_shipping_method` | string | yes | — | Operational | ok |
| 24 | `selected_payment_gateway` | string | yes | — | Operational | ok |
| 25 | `subtotal` | unsignedBigInt | no | `0` | Money | ok |
| 26 | `discount_total` | unsignedBigInt | no | `0` | Money | ok |
| 27 | `shipping_total` | unsignedBigInt | no | `0` | Money | ok |
| 28 | `tax_total` | unsignedBigInt | no | `0` | Money | ok |
| 29 | `grand_total` | unsignedBigInt | no | `0` | Money | ok |
| 30 | `currency` | string(3) | no | `MYR` | Money | ok |
| 31 | `expires_at` | timestampTz | yes | — | **Lifecycle** — scheduled expiry deadline | ok |
| 32 | `completed_at` | timestampTz | yes | — | **Lifecycle** — `Pending|Processing|…` → `Completed` | ok |
| 33 | `created_at` | timestampTz | no | — | **Lifecycle** — session creation | ok |
| 34 | `updated_at` | timestampTz | no | — | Standard | ok |
| — | `cancelled_at` | — | — | — | **MISSING** | |
| — | `payment_failed_at` | — | — | — | **MISSING** | |

### State Machine Reference (CheckoutState)

Only business-critical terminal states and failures get timestamp columns. Transient states (Pending, Processing, AwaitingPayment, PaymentProcessing) do not record transitions.

| State | isTerminal() | canCancel() | canModify() | canRetryPayment() | Timestamp Column |
|-------|:---:|:---:|:---:|:---:|------------------|
| `Pending` | — | yes | yes | — | `created_at` (creation) |
| `Processing` | — | yes | yes | — | (transient — no timestamp) |
| `AwaitingPayment` | — | yes | — | — | (transient — no timestamp) |
| `PaymentProcessing` | — | — | — | — | (transient — no timestamp) |
| `PaymentFailed` | — | yes | yes | yes | `payment_failed_at` |
| `Completed` | yes | — | — | — | `completed_at` |
| `Cancelled` | yes | — | — | — | `cancelled_at` |
| `Expired` | yes | — | — | — | (terminal — tracked via `expires_at` deadline) |

---

## Problems Summary

### P1 — `cancelled_at` missing (HIGH)

The `Cancelled` state is terminal but has no transition timestamp. Both `transitionStatus()` (`CheckoutSession.php:202`) and the `booted()` `updating` callback (`CheckoutSession.php:285`) only record timestamps for `Completed`. A cancelled checkout has no audit trail of *when* cancellation occurred.

**Impact:** Cannot answer "when was this session cancelled?" without parsing `activitylog` entries.

### P2 — `payment_failed_at` missing (HIGH)

`PaymentFailed` is a long-lived retryable state. No timestamp exists to record when the most recent payment failure occurred. The `payment_attempts` counter increments but leaves no time correlation.

**Impact:** Cannot compute "how long has this session been in a failed payment state?" or analyze payment failure timing patterns.

### P3 — `transitionStatus()` only sets `completed_at` (LOW)

The model method at `CheckoutSession.php:202-237` contains a hardcoded `is_a($stateClass, Completed::class, true)` check and only sets `completed_at`. No other state transition timestamps are recorded through this method.

### P4 — `booted()` `updating` only handles `Completed` (LOW)

The Eloquent `updating` event at `CheckoutSession.php:285-288` only sets `completed_at` when the status becomes `Completed`. This is the fallback path for normal `->save()` status changes, but only covers one of three terminal states.

### P5 — No `is_*` boolean anti-patterns found (NONE)

Confirmed across migration, model, and all JSON payload columns. No conversion needed.

### P6 — No `is_public` → `visibility` conversion needed (NONE)

No visibility column exists. Not applicable to this package.

---

## Recommended Structure

### Migration (new columns only — appended in a new migration file)

```php
// Business-critical lifecycle transition timestamps
$table->timestampTz('cancelled_at')->nullable()->index();
$table->timestampTz('payment_failed_at')->nullable();
```

Note: `expires_at` is retained as-is — its role as the scheduled deadline is correct.

### Model — `casts()` additions

```php
'cancelled_at'      => 'immutable_datetime',
'payment_failed_at' => 'immutable_datetime',
```

### Model — `$fillable` additions

```php
'cancelled_at',
'payment_failed_at',
```

### Model — `@property` PHPDoc additions

```php
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $payment_failed_at
```

### Model — `transitionStatus()` rework

Replace the hardcoded `Completed` check with a state-to-timestamp mapping for terminal/failure states only:

```php
public function transitionStatus(string $stateClass): self
{
    $this->status->transitionTo($stateClass);

    $updates = [
        'status'     => $stateClass::getMorphClass(),
        'updated_at' => CarbonImmutable::now(),
    ];

    // Map state classes to their lifecycle timestamp columns (terminal/failure only)
    $timestampMap = [
        Completed::class     => 'completed_at',
        Cancelled::class     => 'cancelled_at',
        PaymentFailed::class => 'payment_failed_at',
    ];

    foreach ($timestampMap as $class => $column) {
        if (is_a($stateClass, $class, true)) {
            $updates[$column] = CarbonImmutable::now();
            break;
        }
    }

    // Direct DB update (existing pattern)
    $this->getConnection()
        ->table($this->getTable())
        ->where($this->getKeyName(), $this->getKey())
        ->update($updates);

    $this->forceFill(['status' => $stateClass]);

    foreach ($timestampMap as $class => $column) {
        if (array_key_exists($column, $updates)) {
            $this->{$column} = $updates[$column];
        }
    }

    $this->updated_at = $updates['updated_at'];

    unset($this->classCastCache['status'], $this->attributeCastCache['status']);
    $this->syncOriginal();

    return $this;
}
```

### Model — `booted()` `updating` rework

```php
static::updating(function (CheckoutSession $session): void {
    if (! $session->isDirty('status')) {
        return;
    }

    $status = $session->status;

    if ($status instanceof Completed) {
        $session->completed_at = CarbonImmutable::now();
    } elseif ($status instanceof Cancelled) {
        $session->cancelled_at = CarbonImmutable::now();
    } elseif ($status instanceof PaymentFailed) {
        $session->payment_failed_at = CarbonImmutable::now();
    }
});
```

---

## Refactoring Plan

Each item is independently executable. Order within a phase is dependency-free except where noted.

### Phase 1 — Schema (1 migration)

- [x] **1.1** Create new migration `add_lifecycle_timestamps_to_checkout_sessions_table` adding 2 columns:
  - `cancelled_at timestampTz nullable` (with index)
  - `payment_failed_at timestampTz nullable`

### Phase 2 — Model (1 file)

- [x] **2.1** `CheckoutSession.php` — add `$fillable` entries for all 2 new columns
- [x] **2.2** `CheckoutSession.php` — add `casts()` entries for all 2 new columns (`'immutable_datetime'`)
- [x] **2.3** `CheckoutSession.php` — add `@property` PHPDoc entries for all 2 new columns
- [x] **2.4** `CheckoutSession.php` — rework `transitionStatus()` with state-to-timestamp map (see Recommended Structure)
- [x] **2.5** `CheckoutSession.php` — rework `booted()` `updating` to handle `Completed`, `Cancelled`, `PaymentFailed`

### Phase 3 — Tests

- [x] **3.1** Add test: transitioning to `Cancelled` sets `cancelled_at` timestamp
- [x] **3.2** Add test: transitioning to `PaymentFailed` sets `payment_failed_at` timestamp
- [x] **3.3** Add test: `Completed` still sets `completed_at` (regression)
- [x] **3.4** Run full test suite: `./vendor/bin/pest --parallel packages/checkout/tests`

---

## Migration Strategy

### New migration file

```
packages/checkout/database/migrations/
  2024_01_01_000002_add_lifecycle_timestamps_to_checkout_sessions_table.php
```

No backfill required — all new columns are `nullable()` and will be `NULL` for existing rows. Existing rows predate this audit; retroactive timestamps cannot be meaningfully computed without external data (activity logs, payment gateway logs). Accept that historical rows will have `NULL` in new lifecycle columns.

### No boolean-to-timestamp conversions needed

No `is_*` boolean columns exist. No DROP COLUMN operations are required.

### Rollback

No `down()` method required (per project convention: migrations are safe/idempotent, no `down()` needed).

---

## Verification Commands

```bash
# 1. Confirm migration creates expected columns
php artisan migrate --pretend --path=packages/checkout/database/migrations

# 2. Run checkout package tests (parallel required)
./vendor/bin/pest --parallel packages/checkout/tests

# 3. PHPStan on checkout package (level 6)
./vendor/bin/phpstan analyse packages/checkout/src --level=6

# 4. Pint formatting on changed files
./vendor/bin/pint packages/checkout/src/Models/CheckoutSession.php
./vendor/bin/pint packages/checkout/database/migrations/

# 5. Verify no boolean lifecycle anti-patterns remain
rg -n "is_(?!step)" packages/checkout/database/migrations
rg -n "is_(?!step)" packages/checkout/src/Models

# 6. Verify all state transition timestamps are recorded in transitionStatus()
rg -n "transitionStatus\|booted" packages/checkout/src/Models/CheckoutSession.php

# 7. Verify PHPDoc properties match migration columns
rg -n "@property.*CarbonImmutable" packages/checkout/src/Models/CheckoutSession.php
```
