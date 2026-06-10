# Cashier Lifecycle Audit & Refactoring Plan

## 1. Executive Summary

The `cashier` package provides a unified multi-gateway billing abstraction over Stripe and CHIP. It owns no database tables of its own — subscription and invoice data lives in gateway-specific tables (`subscriptions` for Stripe via `laravel/cashier`, `chip_subscriptions` for CHIP via `cashier-chip`). The two models (`UnifiedSubscriptionRecord`, `UnifiedInvoiceRecord`) are empty shells: no columns, no timestamps, no owner scoping, no relationships.

The package has a strong contract surface (12 contracts, 5 actions, 10 domain events) and a clean enum vocabulary (`SubscriptionStatus`, `InvoiceStatus`), but the data layer is missing. Status is computed on-the-fly from gateway-native fields rather than persisted as an authoritative lifecycle. Timestamps are absent. Owner scoping is not applied at the model level.

**Goal**: Do NOT own tables. Keep thin abstraction over gateway-owned data (`laravel/cashier` for Stripe, `cashier-chip` for CHIP). Status is computed from gateway-native fields. Lifecycle is managed by gateway-specific packages, not by the abstraction layer. Migrate enums to `src/Enums/` with BC aliases. Keep DTO patterns for unified access.

---

## 2. Full Inventory by Table

### 2.1 `subscriptions` (UnifiedSubscriptionRecord)

**Current model**: `packages/cashier/src/Models/UnifiedSubscriptionRecord.php:10`

| Aspect | Current State | Problem |
|--------|--------------|---------|
| Table | `subscriptions` (gateway-owned) | No package-owned table; depends on laravel/cashier migration |
| PK | UUID via `HasUuids` | OK |
| Timestamps | `$timestamps = false` | No `created_at`, no `updated_at` |
| Status | None on model; derived via `SubscriptionStatus` enum in `Support/SubscriptionStatus.php` | Status is not a persisted column, computed from gateway fields |
| Lifecycle timestamps | `UnifiedSubscription` DTO exposes `trialEndsAt`, `endsAt`, `nextBillingDate`, `createdAt` — all hydrated from gateway-native columns | No package-owned timestamp columns |
| Owner scoping | None; `OwnerScopedQuery` applies ad-hoc scoping via `user_id` or `billable_type/billable_id` subqueries | No `HasOwner` trait, no `owner_type`/`owner_id` columns |
| Relationships | None defined | No relation to billable, no relation to items |
| Gateway columns | External ID resolved dynamically via `getExternalId()` | No dedicated `gateway`/`gateway_id` columns on model |

**Status lifecycle states** (from `SubscriptionStatus` enum, `packages/cashier/src/Support/SubscriptionStatus.php:7`):

```
Incomplete → Active/OnTrial → PastDue → Canceled/OnGracePeriod
                  ↓                          ↓
               Paused                     Expired
```

Allowed transitions (currently only defined via helper methods on the enum):
- `isCancelable()`: `Active`, `OnTrial`, `PastDue`
- `isResumable()`: `OnGracePeriod`, `Paused`
- `isActive()`: `Active`, `OnTrial`, `OnGracePeriod`

spatie/model-states is appropriate here (8 states with guarded transitions).

### 2.2 `purchases` (UnifiedInvoiceRecord)

**Current model**: `packages/cashier/src/Models/UnifiedInvoiceRecord.php:10`

| Aspect | Current State | Problem |
|--------|--------------|---------|
| Table | `purchases` (gateway-owned) | No package-owned table |
| PK | UUID via `HasUuids` | OK |
| Timestamps | `$timestamps = false` | No `created_at`, no `updated_at` |
| Status | None on model; derived via `InvoiceStatus` enum in `Support/InvoiceStatus.php` | Status not persisted |
| Lifecycle timestamps | `UnifiedInvoice` DTO exposes `date`, `dueDate`, `paidAt` — hydrated from gateway data | No package-owned columns |
| Owner scoping | None | No `HasOwner` trait |
| Relationships | None defined | No relation to billable |
| Gateway columns | None | No `gateway`/`gateway_id` columns |

**Invoice lifecycle states** (from `InvoiceStatus` enum, `packages/cashier/src/Support/InvoiceStatus.php:7`):

```
Draft → Open → Paid
          ↓
        Void
          ↓
     Uncollectible
```

Missing: A `Refunded` state is implied by `PaymentRefunded` event. spatie/model-states is appropriate (6 states with guarded transitions).

### 2.3 `billable` model columns (on User/tenant model, not cashier-owned)

These columns live on the billable model (e.g., `App\Models\User`) and are accessed by the Billable trait:

| Column | Used by | Notes |
|--------|---------|-------|
| `stripe_id` | `ManagesGateway::gatewayId()` | Stripe customer ID |
| `chip_id` | `ManagesGateway::gatewayId()` → `resolveChipGatewayId()` | CHIP customer ID (via `chipId()` method) |
| `preferred_gateway` | `ManagesGateway::preferredGateway()` | Default gateway preference |
| `trial_ends_at` | `Concerns/Billable::onGenericTrial()` | Generic trial on the billable, not subscription |

### 2.4 Enums Inventory (currently in `src/Support/`)

| Enum | File | States | Methods |
|------|------|--------|---------|
| `SubscriptionStatus` | `src/Support/SubscriptionStatus.php` | 8 states | `color()`, `icon()`, `label()`, `isCancelable()`, `isResumable()`, `isActive()` |
| `InvoiceStatus` | `src/Support/InvoiceStatus.php` | 5 states | `color()`, `icon()`, `label()` |

Both should be moved to `src/Enums/` per convention. No `States/` directory exists yet.

---

## 3. Problems Summary

### P1 — No package-owned tables / migrations
**Files**: `packages/cashier/src/Models/UnifiedSubscriptionRecord.php:22`, `packages/cashier/src/Models/UnifiedInvoiceRecord.php:22`

Both models delegate table resolution to gateway packages. The cashier package cannot exist independently — it requires an underlying gateway to provide the table. This violates the monorepo's package independence rule.

**Fix**: Create `packages/cashier/database/migrations/` with a unified schema. Models reference their own tables via `config('cashier.tables.subscriptions')` and `config('cashier.tables.purchases')`.

### P2 — Models are empty shells (no columns, no relationships, no scoping)
**Files**: `packages/cashier/src/Models/UnifiedSubscriptionRecord.php:10-26`, `packages/cashier/src/Models/UnifiedInvoiceRecord.php:10-26`

- No `$fillable`/`$guarded` beyond empty `$guarded = []`
- No relationships defined
- No `HasOwner` trait
- No PHPDoc property annotations
- `$timestamps = false`

### P3 — Status not persisted; lifecycle inferred from gateway data
**Files**: `packages/cashier/src/Support/UnifiedSubscription.php:238-303`, `packages/cashier/src/Support/UnifiedInvoice.php:99-127`

Status normalization logic lives in DTO factory methods (`normalizeStripeStatus`, `normalizeChipStatus`, `normalizeStripeInvoiceStatus`, `normalizeChipInvoiceStatus`). Each gateway status string is mapped to the unified enum. This means:
- No single source of truth for subscription/invoice status
- Status cannot be queried directly in SQL without joining gateway-specific tables
- Status transitions are not auditable

**Fix**: Persist `status` as a column. Gateway adapters update it during sync/webhook handling.

### P4 — Missing `timestampTz` columns
**Files**: `packages/cashier/src/Models/UnifiedSubscriptionRecord.php:14`, `packages/cashier/src/Models/UnifiedInvoiceRecord.php:14`

Both models set `$timestamps = false`. Required timestamps per table:

| Table | Missing timestamps |
|-------|--------------------|
| `subscriptions` | `created_at`, `updated_at`, `trial_ends_at`, `current_period_start_at`, `current_period_end_at`, `ends_at`, `paused_at`, `canceled_at`, `expired_at` |
| `purchases` | `created_at`, `updated_at`, `issued_at`, `due_at`, `paid_at`, `voided_at` |

All should use `timestampTz` for PostgreSQL compatibility.

### P5 — No owner scoping on models
**Files**: `packages/cashier/src/Models/UnifiedSubscriptionRecord.php:10`, `packages/cashier/src/Models/UnifiedInvoiceRecord.php:10`

Owner scoping is applied ad-hoc via `OwnerScopedQuery` (`packages/cashier/src/Support/OwnerScopedQuery.php`) which sub-queries through `user_id` or `billable_type/billable_id` columns on gateway tables. The models themselves don't use `HasOwner` from `commerce-support`.

**Fix**: Add `owner_type`/`owner_id` columns to both tables. Models use `HasOwner` trait.

### P6 — Enums in wrong directory
**Files**: `packages/cashier/src/Support/SubscriptionStatus.php`, `packages/cashier/src/Support/InvoiceStatus.php`

Enums should live in `src/Enums/` per the monorepo convention. Currently in `src/Support/`.

### P7 — No spatie/laravel-model-states State classes
The `SubscriptionStatus` enum has an implicit state machine (transitions like Cancelable, Resumable) but no formal `States/` directory with Spatie state classes. This prevents proper transition guards. Both subscription (8 states) and invoice (6 states) qualify for spatie/model-states.

### P8 — `InvoiceStatus` missing `Refunded`
**File**: `packages/cashier/src/Support/InvoiceStatus.php:7`

Only 5 states: `Paid`, `Open`, `Draft`, `Void`, `Uncollectible`. A `Refunded` state is implied by `PaymentRefunded` event but absent from `InvoiceStatus`.

### P9 — `billingCycle()` inferred from plan name string
**File**: `packages/cashier/src/Support/UnifiedSubscription.php:87-100`

Uses `str_contains($planLower, 'annual')` etc. This is fragile. Should be a persisted `interval`/`interval_count` column for data integrity in subscription lifecycle management.

---

## 4. Recommended Structure

### 4.1 New file layout

```
packages/cashier/
├── config/
│   └── cashier.php                    # Updated: add tables, status config
├── database/
│   └── migrations/
│       ├── 0001_01_01_000000_create_subscriptions_table.php
│       └── 0001_01_01_000001_create_purchases_table.php
├── src/
│   ├── Enums/
│   │   ├── SubscriptionStatus.php     # Moved from Support/
│   │   └── InvoiceStatus.php          # Moved from Support/
│   ├── States/
│   │   ├── Subscription/
│   │   │   ├── SubscriptionState.php        # Abstract base state
│   │   │   ├── Incomplete.php
│   │   │   ├── Active.php
│   │   │   ├── OnTrial.php
│   │   │   ├── PastDue.php
│   │   │   ├── OnGracePeriod.php
│   │   │   ├── Paused.php
│   │   │   ├── Canceled.php
│   │   │   └── Expired.php
│   │   └── Invoice/
│   │       ├── InvoiceState.php       # Abstract base state
│   │       ├── Draft.php
│   │       ├── Open.php
│   │       ├── Paid.php
│   │       ├── Void.php
│   │       ├── Uncollectible.php
│   │       └── Refunded.php
│   ├── Models/
│   │   ├── UnifiedSubscriptionRecord.php   # Updated with columns, HasOwner, timestamps
│   │   ├── UnifiedInvoiceRecord.php        # Updated with columns, HasOwner, timestamps
│   │   └── SubscriptionItem.php            # New: line items on unified subscriptions
│   └── Support/
│       ├── SubscriptionStatus.php          # BC alias / deprecated, re-exports Enum
│       └── InvoiceStatus.php               # BC alias / deprecated, re-exports Enum
```

### 4.2 `subscriptions` table — recommended schema

| Column | Type | Purpose |
|--------|------|---------|
| `id` | `uuid` PK | Primary key |
| `gateway` | `varchar(20)` NOT NULL | Gateway name (`stripe`, `chip`) |
| `gateway_id` | `varchar(255)` NOT NULL | External ID from gateway |
| `gateway_status` | `varchar(50)` | Raw gateway status (for audit) |
| `owner_type` | `varchar(255)` NOT NULL | Owner morph type |
| `owner_id` | `uuid` NOT NULL | Owner morph ID |
| `billable_type` | `varchar(255)` NOT NULL | Billable model class |
| `billable_id` | `uuid` NOT NULL | Billable model ID |
| `type` | `varchar(50)` NOT NULL DEFAULT 'default' | Subscription type/name |
| `status` | `varchar(50)` NOT NULL | `SubscriptionStatus` value |
| `plan_id` | `varchar(255)` | Plan/price ID |
| `amount` | `integer` NOT NULL DEFAULT 0 | Total in minor units |
| `currency` | `char(3)` NOT NULL DEFAULT 'MYR' | ISO 4217 currency |
| `quantity` | `integer` NOT NULL DEFAULT 1 | Quantity |
| `interval` | `varchar(20)` | `day`, `week`, `month`, `year` |
| `interval_count` | `integer` DEFAULT 1 | Interval multiplier |
| `trial_ends_at` | `timestampTz` | When trial ends |
| `current_period_start_at` | `timestampTz` | Current billing period start |
| `current_period_end_at` | `timestampTz` | Current billing period end |
| `ends_at` | `timestampTz` | When subscription ends (canceled at period end) |
| `paused_at` | `timestampTz` | When subscription was paused |
| `canceled_at` | `timestampTz` | When cancellation was requested |
| `expired_at` | `timestampTz` | When subscription expired |
| `created_at` | `timestampTz` NOT NULL | Row creation |
| `updated_at` | `timestampTz` NOT NULL | Row last update |
| `metadata` | `jsonb` DEFAULT '{}' | Arbitrary gateway metadata |

Indexes: `(owner_type, owner_id)`, `(gateway, gateway_id)` unique, `(status)`, `(billable_type, billable_id)`, `(current_period_end_at)`.

### 4.3 `purchases` table — recommended schema

| Column | Type | Purpose |
|--------|------|---------|
| `id` | `uuid` PK | Primary key |
| `gateway` | `varchar(20)` NOT NULL | Gateway name |
| `gateway_id` | `varchar(255)` NOT NULL | External ID from gateway |
| `gateway_status` | `varchar(50)` | Raw gateway status (for audit) |
| `owner_type` | `varchar(255)` NOT NULL | Owner morph type |
| `owner_id` | `uuid` NOT NULL | Owner morph ID |
| `billable_type` | `varchar(255)` NOT NULL | Billable model class |
| `billable_id` | `uuid` NOT NULL | Billable model ID |
| `number` | `varchar(255)` | Invoice/purchase number |
| `status` | `varchar(50)` NOT NULL | `InvoiceStatus` value |
| `amount` | `integer` NOT NULL DEFAULT 0 | Total in minor units |
| `currency` | `char(3)` NOT NULL DEFAULT 'MYR' | ISO 4217 currency |
| `subtotal` | `integer` NOT NULL DEFAULT 0 | Subtotal in minor units |
| `tax` | `integer` NOT NULL DEFAULT 0 | Tax in minor units |
| `issued_at` | `timestampTz` | Invoice issue date |
| `due_at` | `timestampTz` | Payment due date |
| `paid_at` | `timestampTz` | When payment was completed |
| `voided_at` | `timestampTz` | When voided |
| `refunded_at` | `timestampTz` | When refunded |
| `created_at` | `timestampTz` NOT NULL | Row creation |
| `updated_at` | `timestampTz` NOT NULL | Row last update |
| `metadata` | `jsonb` DEFAULT '{}' | Arbitrary gateway metadata |

Indexes: `(owner_type, owner_id)`, `(gateway, gateway_id)` unique, `(status)`, `(billable_type, billable_id)`, `(issued_at)`.

### 4.4 State machine diagrams

**Subscription lifecycle** (formalized via `spatie/laravel-model-states`):

```
                   ┌──────────────┐
                   │  Incomplete  │
                   └──────┬───────┘
                          │ payment confirmed
                   ┌──────▼───────┐
            ┌──────│    Active    │──────────┐
            │      └──────┬───────┘          │
            │             │ payment fails    │
            │      ┌──────▼───────┐          │
            │      │   PastDue    │          │
            │      └──────┬───────┘          │
            │             │                  │
     trial  │      ┌──────▼───────┐   ┌──────▼───────┐
     starts │      │   Canceled   │   │ OnGracePeriod│
            │      └──────────────┘   └──────┬───────┘
            │                                │ ends_at past
     ┌──────▼───────┐                 ┌──────▼───────┐
     │   OnTrial    │                 │   Expired    │
     └──────┬───────┘                 └──────────────┘
            │ trial ends, payment OK
            └────────────────────► Active

     Active ─────────────────────► Paused
     Paused ─────────────────────► Active (resume)
```

**Invoice lifecycle**:

```
     Draft ──► Open ──► Paid
                 │         │
                 ▼         ▼
               Void    Refunded
                 │
                 ▼
           Uncollectible
```

### 4.5 Config additions

Add to `config/cashier.php`:

```php
'tables' => [
    'subscriptions' => 'subscriptions',
    'purchases' => 'purchases',
    'subscription_items' => 'subscription_items',
],

'subscriptions' => [
    'owner_scope' => [
        'enabled' => env('CASHIER_SUBSCRIPTION_OWNER_SCOPE', true),
        'auto_assign_on_create' => env('CASHIER_SUBSCRIPTION_AUTO_ASSIGN_OWNER', true),
    ],
],

'purchases' => [
    'owner_scope' => [
        'enabled' => env('CASHIER_PURCHASE_OWNER_SCOPE', true),
        'auto_assign_on_create' => env('CASHIER_PURCHASE_AUTO_ASSIGN_OWNER', true),
    ],
],

'status' => [
    'subscription' => SubscriptionStatus::class,
    'invoice' => InvoiceStatus::class,
],
```

---

## 5. Refactoring Plan — Parallel-Agent Checklist

All items are independent within their phase (can run in parallel). Items within a phase must complete before the next phase starts.

### Phase A — Foundation

- [x] **A1**: Move enums to `src/Enums/` with BC aliases in `Support/`
- [x] **A2**: Create BC alias files (`Support/SubscriptionStatus.php`, `Support/InvoiceStatus.php`)

> **Note**: `packages/cashier` is a thin abstraction layer over gateway-owned tables (`laravel/cashier` for Stripe, `cashier-chip` for CHIP). It does NOT own its own tables, models, or state machines. Table creation, model ownership, and spatie/model-states were proposed in an earlier draft of this audit but were [REVERTED] because they:
> - Conflict with `laravel/cashier`'s own `subscriptions` table
> - Duplicate data already stored in gateway packages
> - Violate the abstraction layer design pattern
>
> Lifecycle concerns for subscriptions/purchases are addressed in the gateway-specific packages (`cashier-chip`, `chip`, `laravel/cashier`).

- [ ] **B2**: Write purchases migration — [REVERTED] same reason as B1

### Phase C — Enums (can run in parallel)

- [x] **C1**: Move `Support/SubscriptionStatus.php` → `Enums/SubscriptionStatus.php`
  - Add `TransitionAllowed` and `TransitionTo*` helper methods per state
  - Keep BC alias at `Support/SubscriptionStatus.php` that re-exports the enum

- [x] **C2**: Move `Support/InvoiceStatus.php` → `Enums/InvoiceStatus.php`
  - Add `Refunded` case
  - Keep BC alias at `Support/InvoiceStatus.php`

### Phase D — States — [REVERTED] no state machines in abstraction layer

- [ ] **D1-D4**: spatie/model-states — [REVERTED] cashier does not own data

### Phase E — Models — [REVERTED] no owned models in abstraction layer

- [ ] **E1-E3**: Model changes — [REVERTED] cashier uses DTOs over gateway-owned models

### Phase F — Config

- [ ] **F1**: Config changes — [REVERTED] tables/owner_scope config removed

### Phase G — Support/DTO updates

- [ ] **G1**: Update `UnifiedSubscription` DTO — [REVERTED] DTOs still read from gateway data via attribute access (correct for abstraction layer)

- [ ] **G2**: Update `UnifiedInvoice` DTO — [REVERTED] same reason as G1

### Phase H — Tests & Verification — [REVERTED] no owned tables means no migration/state tests needed

- [ ] **H1-H5**: Test items — [REVERTED] no tables/models to test

---

## 7. Verification Commands

```bash
# PHPStan level 6
./vendor/bin/phpstan analyse packages/cashier/src --level=6

# Enum check
rg -n "case [a-z]" packages/cashier/src/Enums
```
