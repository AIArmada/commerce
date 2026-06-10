# Pricing Package — Lifecycle Audit

## 1. Executive Summary

The pricing package manages three tables — `price_lists`, `prices`, and `price_tiers` — which are **configuration entities**. Per lifecycle principles, admin configuration toggles should keep `is_active` / `is_default` booleans (or at most add a single `deactivated_at timestampTz`). The `starts_at`/`ends_at` scheduling windows on price lists and prices are already correct patterns for time-bound configuration.

**No `status` columns are needed** on configuration entities. The existing boolean flags + scheduling windows are the appropriate lifecycle representation.

---

## 2. Full Inventory by Table

---

### 2.1 `price_lists` (Model: `PriceList`)

| Column | Type | Current Meaning | Assessment |
|---|---|---|---|
| `id` | `uuid` | Primary key | OK |
| `name` | `string` | Human-readable name | OK |
| `slug` | `string` | URL-safe identifier, unique | OK |
| `description` | `text` nullable | Optional description | OK |
| `currency` | `string(3)` | ISO 4217 currency code, default `MYR` | OK |
| `priority` | `integer` | Sort priority (higher = earlier match) | OK |
| `is_default` | `boolean` | Whether this is the default price list | **OK** — config designation, keep boolean |
| `is_active` | `boolean` | Administrative on/off toggle | **OK** — config toggle, keep boolean |
| `customer_id` | `foreignUuid` nullable | Link to customer for customer-specific lists | OK |
| `segment_id` | `foreignUuid` nullable | Link to segment for segment-specific lists | OK |
| `starts_at` | `timestampTz` nullable | Scheduled activation window start | OK — correct pattern |
| `ends_at` | `timestampTz` nullable | Scheduled activation window end | OK — correct pattern |
| `owner_type` | `string` nullable | Tenant owner morph type | OK |
| `owner_id` | `uuid` nullable | Tenant owner morph id | OK |
| `created_at` | `timestampTz` | Row creation | OK |
| `updated_at` | `timestampTz` | Row last update | OK |

**Assessment: Correct pattern.** `is_active` + `starts_at`/`ends_at` is the appropriate lifecycle representation for a configuration entity. Optionally, a single `deactivated_at timestampTz` could be added for audit trail without adding a `status` column.

---

### 2.2 `prices` (Model: `Price`)

| Column | Type | Current Meaning | Assessment |
|---|---|---|---|
| `id` | `uuid` | Primary key | OK |
| `price_list_id` | `foreignUuid` | Parent price list | OK |
| `priceable_type` | `string` | Polymorphic target type | OK |
| `priceable_id` | `uuid` | Polymorphic target id | OK |
| `amount` | `unsignedBigInteger` | Price in minor units (cents) | OK |
| `compare_amount` | `unsignedBigInteger` nullable | Strike-through/original price | OK |
| `currency` | `string(3)` | ISO 4217, default `MYR` | OK |
| `min_quantity` | `unsignedInteger` | Min quantity for this price to apply | OK |
| `starts_at` | `timestampTz` nullable | Scheduled activation window start | OK — correct pattern |
| `ends_at` | `timestampTz` nullable | Scheduled activation window end | OK — correct pattern |
| `owner_type` | `string` nullable | Tenant owner morph type | OK |
| `owner_id` | `uuid` nullable | Tenant owner morph id | OK |
| `created_at` | `timestampTz` | Row creation | OK |
| `updated_at` | `timestampTz` | Row last update | OK |

**Assessment: Correct pattern.** The `starts_at`/`ends_at` scheduling window is sufficient for time-bound pricing. Active state is derived from the scheduling window. No `status` column needed.

---

### 2.3 `price_tiers` (Model: `PriceTier`)

| Column | Type | Current Meaning | Assessment |
|---|---|---|---|
| `id` | `uuid` | Primary key | OK |
| `price_list_id` | `foreignUuid` nullable | Optional parent price list | OK |
| `tierable_type` | `string` | Polymorphic target type | OK |
| `tierable_id` | `uuid` | Polymorphic target id | OK |
| `min_quantity` | `unsignedInteger` | Lower bound of tier range | OK |
| `max_quantity` | `unsignedInteger` nullable | Upper bound of tier range (null = no cap) | OK |
| `amount` | `unsignedBigInteger` | Tier price in minor units (cents) | OK |
| `currency` | `string(3)` | ISO 4217, default `MYR` | OK |
| `discount_type` | `string` nullable | `'percentage'` or `'fixed'` | OK — string is fine for 2-value classification |
| `discount_value` | `unsignedBigInteger` nullable | Discount amount (% or minor units) | OK |
| `owner_type` | `string` nullable | Tenant owner morph type | OK |
| `owner_id` | `uuid` nullable | Tenant owner morph id | OK |
| `created_at` | `timestampTz` | Row creation | OK |
| `updated_at` | `timestampTz` | Row last update | OK |

**Assessment: OK as-is.** Price tiers are structural definitions within a price list and inherit lifecycle from the parent price list. No `status` or scheduling columns needed.

---

## 3. Problems Summary

| # | Table | Severity | Problem |
|---|---|---|---|
| P1 | `price_lists` | Low | No `deactivated_at` timestampTz — optional audit improvement for the `is_active` toggle |
| P2 | `prices` | Low | No `deactivated_at` timestampTz — optional audit improvement when explicitly overriding scheduling |
| P3 | `price_tiers` | Low | No `is_active` boolean — could optionally add for explicit enable/disable |

---

## 4. Recommended Structure

### 4.1 `price_lists` — Optional Additions

The existing schema is correct for a configuration entity. Optional improvement:

```
deactivated_at   timestampTz NULL   -- OPTIONAL: set when is_active transitions to false
```

No `status` column. Keep `is_active` boolean + `starts_at`/`ends_at` scheduling. Keep `is_default` boolean.

### 4.2 `prices` — Optional Additions

The existing schema is correct. Optional improvement:

```
deactivated_at   timestampTz NULL   -- OPTIONAL: set when explicitly deactivated (vs. scheduling expiry)
```

No `status` column. Keep `starts_at`/`ends_at` scheduling.

### 4.3 `price_tiers` — Optional Additions

The existing schema is correct. Optional improvement:

```
is_active        boolean NOT NULL DEFAULT true   -- OPTIONAL: explicit enable/disable toggle
```

No `status` column needed. Tiers inherit lifecycle context from their parent price list.

---

## 5. Refactoring Plan — Optional Improvements

These are **optional** audit improvements for configuration entities. Not required.

### Optional A: `price_lists` — Add `deactivated_at`

- [x] **A1.** Add `deactivated_at` timestampTz nullable column
- [x] **A2.** Update `PriceList` model: add `deactivated_at` to casts
- [x] **A3.** Update deactivation logic to set `deactivated_at = now()`

### Optional B: `prices` — Add `deactivated_at`

- [x] **B1.** Add `deactivated_at` timestampTz nullable column
- [x] **B2.** Update `Price` model: add `deactivated_at` to casts

### Optional C: `price_tiers` — Add `is_active`

- [x] **C1.** Add `is_active` boolean column, default `true`
- [x] **C2.** Update `PriceTier` model: add `is_active` to casts/fillable
- [x] **C3.** Add `scopeActive()` to filter active tiers

---

## 6. Migration Strategy

No required migrations. The existing schema is correct for configuration entities.

Optional migrations should follow the standard pattern:
1. Add nullable columns first (non-breaking)
2. Backfill for existing rows
3. Update model casts and scopes

---

## 7. Verification Commands

```bash
# PHPStan on the pricing package
./vendor/bin/phpstan analyse packages/pricing/src --level=6

# Run all pricing tests in parallel
./vendor/bin/pest --parallel packages/pricing/tests

# Verify no status columns exist on config tables (they shouldn't)
rg -n "status" packages/pricing/database/migrations

# Verify is_active + starts_at/ends_at pattern is intact
rg -n "is_active|starts_at|ends_at" packages/pricing/src/Models
```
