# Cart Package — Lifecycle Audit & Refactoring Plan

## 1. Executive Summary

The cart package has **2 database tables** (`carts`, `conditions`) and **1 value object** (`CartItem`, stored as JSON). The current schema relies on `is_*` booleans for some state but entirely lacks explicit lifecycle transition timestamps on the `carts` table. Cart lifecycle transitions — creation, abandonment, checkout conversion, merging — have no persisted timestamps. Expiration is tracked via `expires_at` (good), but the complementary `expired_at` (when it actually expired) and `abandoned_at` are missing. Cart state should be purely timestamp-driven (no `status` column): `checked_out_at`, `abandoned_at`, and `expired_at` define the cart's lifecycle.

The `Condition` model uses `is_active` and `is_global` booleans — these are admin configuration toggles. Per the config-entity rule, `is_active` stays with an optional `deactivated_at` timestampTz for audit. `is_global` (a scope indicator meaning "auto-applied to all carts") remains a boolean.

**Target**: Add `checked_out_at`, `abandoned_at`, `expired_at`, and `merged_into_id` to carts (no `status` column). Add `deactivated_at` to conditions as an audit timestamp alongside the existing `is_active` boolean. Classification booleans on conditions (`is_charge`, `is_discount`, `is_percentage`, `is_dynamic`) are derived properties and remain as-is.

---

## 2. Full Inventory by Table

### 2.1 `carts` table (`CartModel`)

| Column | Type | Current Lifecycle Role | Problem |
|--------|------|------------------------|---------|
| `id` | uuid PK | — | — |
| `identifier` | varchar | — | — |
| `owner_scope` | varchar | — | — |
| `owner_type` | nullableMorphs | — | — |
| `owner_id` | nullableMorphs | — | — |
| `instance` | varchar | — | — |
| `items` | json | Cart contents; emptiness derived by `isEmpty()` | No explicit lifecycle signal |
| `conditions` | json | — | — |
| `metadata` | json | — | — |
| `version` | int | Optimistic locking | Not lifecycle |
| `expires_at` | timestampTz nullable | Planned expiration (TTL-based) | **Good** — but no `expired_at` for actual expiry time |
| `created_at` | timestampTz | Cart creation | **Ok** |
| `updated_at` | timestampTz | Last modification; used to detect abandonment (`updated_at < cutoff`) | **Ok** but no dedicated `abandoned_at` |

#### Lifecycle transitions observed in code (none persisted):

| Transition | Trigger | Event fired | Persisted? |
|------------|---------|-------------|------------|
| Created | First item added | `CartCreated` | `created_at` only |
| Cleared | All items removed | `CartCleared` | `updated_at` only |
| Merged | Guest→user migration | `CartMerged` | `updated_at` only; source cart deleted |
| Expired | Timer-based | (none) | Only `expires_at` is set; no actual expiry record |
| Abandoned | `cart:clear-abandoned` detects staleness | (none) | Deleted by cleanup command |
| Destroyed | Explicit delete / cleanup | `CartDestroyed` | Row deleted (no audit trail) |
| Converted to order | (not in cart package — handled by checkout) | (none from cart) | **Missing entirely** |

#### Derived "states" (no dedicated column):
- **"active"** = `expires_at IS NULL OR expires_at > now()` AND `items IS NOT NULL AND items != '[]'`
- **"empty"** = `items IS NULL OR items = '[]'`
- **"expired"** = `expires_at <= now()`
- **"abandoned"** = `updated_at < now() - N days` (from `ClearAbandonedCartsCommand`)

### 2.2 `conditions` table (`Condition`)

| Column | Type | Current Lifecycle Role | Problem |
|--------|------|------------------------|---------|
| `id` | uuid PK | — | — |
| `owner_scope` | varchar | — | — |
| `name` | varchar | — | — |
| `display_name` | varchar nullable | — | — |
| `description` | text nullable | — | — |
| `type` | varchar | Condition classifier (discount/tax/fee/shipping) | Not lifecycle |
| `target` | varchar | DSL target string | Not lifecycle |
| `target_definition` | json | Structured payload | Not lifecycle |
| `value` | varchar | e.g. "-10%", "+5" | Not lifecycle |
| `operator` | varchar nullable | Computed from value | Not lifecycle |
| `is_charge` | bool | Derived classification (not lifecycle) | Ok as bool — derived from `value` in `computeDerivedFields()` |
| `is_dynamic` | bool | Derived classification (not lifecycle) | Ok as bool — derived from `rules.factory_keys` |
| `is_discount` | bool | Derived classification (not lifecycle) | Ok as bool — derived from `value` |
| `is_percentage` | bool | Derived classification (not lifecycle) | Ok as bool — derived from `value` |
| `parsed_value` | varchar nullable | Computed from value | Not lifecycle |
| `order` | int | Sort order | Not lifecycle |
| `attributes` | json | — | — |
| `rules` | json | — | — |
| `is_global` | bool | Scope indicator — "auto-applied to all carts" | **Kept as boolean** — config designation, not lifecycle |
| `is_active` | bool | Config toggle — enabled/disabled | **Kept as boolean** — missing `deactivated_at` for audit |
| `created_at` | timestampTz | — | **Ok** |
| `updated_at` | timestampTz | — | **Ok** |

### 2.3 `CartItem` (value object — stored as JSON in `carts.items`)

No database table. No lifecycle columns to audit. The CartItem is an immutable value object; its lifecycle is entirely contained within the parent `CartModel`.

---

## 3. Problems Summary

### P1: No `checked_out_at` on carts
When a cart completes checkout, there is no trace in the cart record. The conversion event is lost from the cart perspective. This prevents auditing the full cart lifecycle and makes it impossible to distinguish "abandoned" from "successfully checked out".

### P2: No `abandoned_at` on carts
The `cart:clear-abandoned` command detects abandonment by comparing `updated_at` to a cutoff. This mixes "last touched" with "definitively abandoned". An explicit `abandoned_at` timestamp would provide a clear signal and allow the command to skip re-evaluating already-marked carts.

### P3: No `expired_at` on carts
`expires_at` is the *planned* expiration time. There is no column recording *when* the cart actually crossed that threshold. This gap prevents distinguishing "expired" from "still active with a future `expires_at`" without a runtime comparison.

### P4: No `merged_into_id` on carts
When a guest cart is merged into a user cart, the source cart is physically deleted (`CartDestroyed`). The trail is lost — there is no forward reference from the deleted cart to the target, and no back-reference from the target to the source(s). Preserving merged carts with a `merged_into_id` reference enables audit.

### P5: `is_active` boolean on conditions lacks `deactivated_at` timestamp
Conditions toggle between active/inactive with no record of when deactivation occurred. A `deactivated_at` timestampTz alongside the existing `is_active` boolean would enable audit trails and time-based filtering (e.g., "show conditions that were active during date range X").

---

## 4. Recommended Structure (Final Column Layout)

### 4.1 `carts` table

Cart lifecycle is purely timestamp-driven. No `status` column — presence of a `*_at` timestamp signals the state.

```
  id                  uuid PRIMARY KEY
  identifier          varchar NOT NULL
  owner_scope         varchar NOT NULL DEFAULT 'global'
  owner_type          varchar NULL
  owner_id            uuid NULL
  instance            varchar NOT NULL DEFAULT 'default'
  items               jsonb NULL
  conditions          jsonb NULL
  metadata            jsonb NULL
  version             integer NOT NULL DEFAULT 1
  expires_at          timestamptz NULL       -- planned expiration (TTL)
  expired_at          timestamptz NULL       -- actual expiry timestamp
  checked_out_at      timestamptz NULL       -- when cart was converted to an order
  abandoned_at        timestamptz NULL       -- when cart was detected as abandoned
  merged_into_id      uuid NULL             -- target cart ID (if this cart was merged into another)
  created_at          timestamptz NOT NULL
  updated_at          timestamptz NOT NULL

  UNIQUE (owner_scope, identifier, instance)
  INDEX (expires_at)
  INDEX (expired_at)
  INDEX (checked_out_at)
  INDEX (abandoned_at)
  INDEX (merged_into_id)
```

#### Lifecycle semantics (timestamp-driven, no status column):

| State | Condition | Timestamp set |
|-------|-----------|---------------|
| Active | `checked_out_at IS NULL AND abandoned_at IS NULL AND expired_at IS NULL` | (created_at) |
| Converted | `checked_out_at IS NOT NULL` | `checked_out_at = now()` |
| Abandoned | `abandoned_at IS NOT NULL` | `abandoned_at = now()` |
| Expired | `expired_at IS NOT NULL` | `expired_at = now()` |
| Merged | `merged_into_id IS NOT NULL` | (merged_into_id = target cart ID) |

Transition rules:
- All `→ converted`: set `checked_out_at`
- All `→ expired`: set `expired_at`
- All `→ abandoned`: set `abandoned_at`
- All `→ merged`: set `merged_into_id`
- Abandoned carts are candidates for later `cart:clear-abandoned` cleanup

### 4.2 `conditions` table

```
  id                  uuid PRIMARY KEY
  owner_scope         varchar NOT NULL DEFAULT 'global'
  name                varchar NOT NULL
  display_name        varchar NULL
  description         text NULL
  type                varchar NOT NULL           -- discount, tax, fee, shipping, etc.
  target              varchar NOT NULL           -- DSL target string
  target_definition   jsonb NOT NULL             -- structured scope/phase/application
  value               varchar NOT NULL           -- e.g. "-10%", "+5"
  operator            varchar NULL               -- computed: +, -, *, /, %
  is_charge           boolean NOT NULL DEFAULT false  -- [KEPT] derived classification
  is_dynamic          boolean NOT NULL DEFAULT false  -- [KEPT] derived classification
  is_discount         boolean NOT NULL DEFAULT false  -- [KEPT] derived classification
  is_percentage       boolean NOT NULL DEFAULT false  -- [KEPT] derived classification
  parsed_value        varchar NULL               -- computed from value
  order               integer NOT NULL DEFAULT 0
  attributes          jsonb NULL
  rules               jsonb NULL
  is_active           boolean NOT NULL DEFAULT true   -- [KEPT] config toggle
  is_global           boolean NOT NULL DEFAULT false  -- [KEPT] scope indicator
  deactivated_at      timestamptz NULL           -- when is_active was set to false
  owner_type          varchar NULL
  owner_id            uuid NULL
  created_at          timestamptz NOT NULL
  updated_at          timestamptz NOT NULL

  UNIQUE (owner_scope, name)
  INDEX (type, is_active)
  INDEX (target, is_active)
  INDEX (is_active)
  INDEX (is_global)
  INDEX (deactivated_at)
```

#### New column only:
- `deactivated_at`: set when `is_active` changes from true → false. Null when active.

#### Kept booleans (non-lifecycle):
- `is_active` — config toggle (admin enables/disables a condition). Kept as boolean.
- `is_global` — scope indicator (auto-applied to all carts vs owner-scoped). Kept as boolean.
- `is_charge`, `is_discount`, `is_percentage`, `is_dynamic` — computed from `value` / `rules` by `computeDerivedFields()`. They describe inherent properties of the condition definition, not mutable lifecycle state.

---

## 5. Refactoring Plan

### Checklist A: `carts` table — new columns

- [x] **A1.** Create migration: add `expired_at`, `checked_out_at`, `abandoned_at`, `merged_into_id`
- [x] **A2.** Update `CartModel::$fillable` to include new columns
- [x] **A3.** Update `CartModel::casts()` to include new datetime columns
- [x] **A4.** Update `CartModel` PHPDoc `@property` annotations
- [x] **A5.** Add `isExpired()` check: `expired_at IS NOT NULL OR expires_at <= now()` (backward compatible)
- [x] **A6.** Add `isConverted()`, `isAbandoned()`, `isMerged()` helper methods on `CartModel`
- [x] **A7.** Update `expired()` / `notExpired()` scopes to check both `expired_at` and `expires_at`
- [x] **A8.** Update `ClearAbandonedCartsCommand` to:
  - Set `abandoned_at = now()` on detected abandoned carts
  - Skip carts that already have `abandoned_at` set
- [x] **A9.** Add `--delete` flag to `cart:clear-abandoned` to physically delete carts with `abandoned_at` set (separate from detection)
- [x] **A10.** Update `MigrateGuestCartToUserAction` to set `merged_into_id` on source cart
- [x] **A11.** Add indexes for new columns (see §4.1)

### Checklist B: `cart` lifecycle — checkout integration

- [x] **B1.** Define an interface/event for checkout to signal cart conversion
- [x] **B2.** Implement `markAsConverted()` on `CartModel` — sets `checked_out_at = now()`
- [x] **B3.** Wire checkout package to call `markAsConverted()` when order is placed
- [x] **B4.** Add `converted()` scope on `CartModel` for analytics

### Checklist C: `conditions` table — deactivated_at

- [x] **C1.** Create migration: add `deactivated_at` timestampTz nullable
- [x] **C2.** Update `Condition::$fillable` — add `deactivated_at`
- [x] **C3.** Update `Condition::casts()` — add `deactivated_at => 'datetime'`
- [x] **C4.** Update `Condition` PHPDoc `@property` annotations
- [x] **C5.** Add `booted()` listener: when `is_active` changes from true → false, set `deactivated_at = now()`
- [x] **C6.** Add `deactivate()` convenience method on `Condition`
- [x] **C7.** Add index for `deactivated_at`

### Checklist D: Filament UI (in `packages/filament-cart`)

- [x] **D1.** Update Cart resource (if exists) to show lifecycle timestamps (checked_out_at, abandoned_at)
- [x] **D2.** Add cart lifecycle filter to cart resource table (converted, abandoned, expired, merged)

### Checklist E: Tests

- [x] **E1.** Add migration test: verify new columns exist on carts
- [x] **E2.** Add test: `CartModel::markAsConverted()` sets `checked_out_at`
- [x] **E3.** Add test: `cart:clear-abandoned` sets `abandoned_at` on stale carts
- [x] **E4.** Add test: `cart:clear-abandoned` skips already-abandoned carts
- [x] **E5.** Add test: merged cart gets `merged_into_id` set
- [x] **E6.** Add test: condition `deactivate()` sets `deactivated_at`
- [x] **E7.** Add test: `expired_at` is set when cart passes its TTL
- [x] **E8.** Run existing tests and fix any broken by new columns

### Checklist F: Documentation

- [x] **F1.** Update `docs/03-configuration.md` — document cart lifecycle timestamps
- [x] **F2.** Update `docs/04-usage.md` — document cart lifecycle and condition deactivation
- [x] **F3.** Update `CONTEXT.md` if lifecycle events changed

---

## 6. Migration Strategy

### Phase 1: Add columns (non-breaking, deploy first)

```
Migration 2000_02_01_000007_add_lifecycle_columns_to_carts_table.php
  → ALTER TABLE carts ADD COLUMN expired_at timestamptz NULL
  → ALTER TABLE carts ADD COLUMN checked_out_at timestamptz NULL
  → ALTER TABLE carts ADD COLUMN abandoned_at timestamptz NULL
  → ALTER TABLE carts ADD COLUMN merged_into_id uuid NULL
  → CREATE INDEX ... (expired_at, checked_out_at, abandoned_at, merged_into_id)

Migration 2000_02_01_000008_add_deactivated_at_to_conditions_table.php
  → ALTER TABLE conditions ADD COLUMN deactivated_at timestamptz NULL
  → CREATE INDEX ... (deactivated_at)
```

### Phase 2: Code switchover (deploy with Phase 2)

- Update models to use new columns
- Update scopes, fillable, casts
- `is_active` and `is_global` booleans on conditions remain unchanged — no DROP needed

### Execution order:

1. Run Phase 1 migrations
2. Deploy Phase 2 code
3. No cleanup migration needed (no columns dropped)

---

## 7. Verification Commands

### After Phase 1 migration:

```bash
# Verify new columns exist on carts
php artisan tinker --execute '
  $cols = Schema::getColumnListing("carts");
  echo in_array("checked_out_at", $cols) ? "OK: carts.checked_out_at" : "MISSING: carts.checked_out_at";
  echo PHP_EOL;
  echo in_array("abandoned_at", $cols) ? "OK: carts.abandoned_at" : "MISSING: carts.abandoned_at";
  echo PHP_EOL;
  echo in_array("expired_at", $cols) ? "OK: carts.expired_at" : "MISSING: carts.expired_at";
'

# Verify deactivated_at on conditions
php artisan tinker --execute '
  $cols = Schema::getColumnListing("conditions");
  echo in_array("deactivated_at", $cols) ? "OK" : "MISSING";
  echo " conditions.deactivated_at\n";
'
```

### After Phase 2 code switchover:

```bash
# PHPStan on cart package
./vendor/bin/phpstan analyse packages/cart/src --level=6

# Pint on changed files
./vendor/bin/pint packages/cart/src/Models/CartModel.php
./vendor/bin/pint packages/cart/src/Models/Condition.php

# Run cart tests
./vendor/bin/pest --parallel packages/cart/tests

# Run filament-cart tests (if Checklist D completed)
./vendor/bin/pest --parallel packages/filament-cart/tests
```

### Full verification sweep:

```bash
# No timestamps without timezone
rg -n "timestamp\(|timestampWithoutTz" packages/cart/database/migrations/

# All *_at columns use timestampTz
rg -n "timestampTz|timestampsTz" packages/cart/database/migrations/

# New columns referenced in model code
rg -n "checked_out_at|abandoned_at|expired_at|merged_into_id" packages/cart/src/Models/CartModel.php
rg -n "deactivated_at" packages/cart/src/Models/Condition.php
```
