# Products Package Lifecycle Audit & Refactoring Plan

## 1. Executive Summary

This package has **13 tables** across migrations and models. Lifecycle state is currently tracked through a mix of `status` enums, `is_*` booleans, scheduling timestamps, and visibility flags. The core problems are:

- **Inconsistent patterns**: Products use `status` enum + `published_at`; Variants use `is_enabled` boolean; Categories/Collections/Options use `is_visible` boolean. No unified convention.
- **Missing transition timestamps**: Only `products` has `published_at`. No table records when a row was disabled, archived, or hidden.
- **`is_*` booleans as lifecycle shorthands**: `is_enabled`, `is_visible` — these are state shadows without audit trail.
- **`is_featured` on catalog entities**: Curation flags — kept as booleans, not lifecycle.
- **`is_default` on variants/sets**: Designation — kept as booleans, not lifecycle.

**Target principles**: `status` describes lifecycle → prefer `deactivated_at`/`hidden_at` timestamps over `is_*` booleans → `is_visible` → `visibility` for catalog entities → add missing transition timestamps → no backward compat → `timestampTz` for PostgreSQL.

---

## 2. Full Inventory by Table

### 2.1 `products` (primary entity)

| Column | Type | Current Purpose | Lifecycle Concern |
|---|---|---|---|
| `status` | `string` (enum cast) | draft / active / disabled / archived | YES — primary lifecycle driver |
| `visibility` | `string` (enum cast) | catalog / search / catalog_search / individual / hidden | YES — display lifecycle |
| `published_at` | `timestampTz` nullable | Set when product first activates | YES — transition timestamp |
| `is_featured` | `boolean` | Featured flag | NO — **curation flag, kept as boolean** |
| `type` | `string` | simple / configurable / bundle / digital / subscription | NO — product archetype, not lifecycle |
| `is_taxable` | `boolean` | Tax behavior flag | NO — business rule |
| `requires_shipping` | `boolean` | Shipping behavior flag | NO — business rule |
| `supports_variants` | `boolean` | Variant capability flag | NO — capability |
| `tracks_inventory` | `boolean` | Inventory behavior flag | NO — capability |

**Enums**: `ProductStatus` (Draft/Active/Disabled/Archived), `ProductVisibility` (Catalog/Search/CatalogSearch/Individual/Hidden)

**Transition logic (UpdateProductStatus Action + Product::activate())**: Sets `published_at` on first Active transition. No other transition timestamps recorded.

**Missing**: `deactivated_at`, `archived_at`

---

### 2.2 `product_variants`

| Column | Type | Current Purpose | Lifecycle Concern |
|---|---|---|---|
| `is_enabled` | `boolean` | Enabled/disabled flag | YES — should be `deactivated_at` timestampTz |
| `is_default` | `boolean` | Default variant marker | NO — **designation, kept as boolean** |

**Missing**: `deactivated_at` timestampTz

---

### 2.3 `product_options`

| Column | Type | Current Purpose | Lifecycle Concern |
|---|---|---|---|
| `is_visible` | `boolean` | Visibility toggle | YES — should be `visibility` string |

**Missing**: `hidden_at` timestampTz

---

### 2.4 `product_option_values`

No lifecycle fields. Supporting entity only. No changes needed.

---

### 2.5 `product_categories`

| Column | Type | Current Purpose | Lifecycle Concern |
|---|---|---|---|
| `is_visible` | `boolean` | Visibility toggle | YES — should be `visibility` string |
| `is_featured` | `boolean` | Featured flag | NO — **curation flag, kept as boolean** |

**Missing**: `visibility` string, `status` enum (active/hidden/archived), `hidden_at`, `archived_at`

---

### 2.6 `product_collections`

| Column | Type | Current Purpose | Lifecycle Concern |
|---|---|---|---|
| `is_visible` | `boolean` | Visibility toggle | YES — should be `visibility` string |
| `is_featured` | `boolean` | Featured flag | NO — **curation flag, kept as boolean** |
| `published_at` | `timestampTz` nullable | Scheduled start datetime | YES — scheduling, not lifecycle |
| `unpublished_at` | `timestampTz` nullable | Scheduled end datetime | YES — scheduling, not lifecycle |
| `type` | `string` | manual / automatic | NO — collection type |

**Note**: `published_at`/`unpublished_at` are scheduling windows, distinct from lifecycle status. A collection can be `active` (status) but not yet `published` (scheduling). Both concepts are valid and should coexist.

**Missing**: `visibility` string, `status` enum, `hidden_at`, `archived_at`

---

### 2.7 `product_attribute_groups`

| Column | Type | Current Purpose | Lifecycle Concern |
|---|---|---|---|
| `is_visible` | `boolean` | Visibility toggle | YES — should be `visibility` string |

**Missing**: `hidden_at` timestampTz

---

### 2.8 `product_attributes`

| Column | Type | Current Purpose | Lifecycle Concern |
|---|---|---|---|
| `is_required` | `boolean` | Validation requirement | NO — validation rule |
| `is_filterable` | `boolean` | Filter availability | NO — behavior flag |
| `is_searchable` | `boolean` | Search inclusion | NO — behavior flag |
| `is_comparable` | `boolean` | Compare inclusion | NO — behavior flag |
| `is_visible_on_front` | `boolean` | Frontend visibility | NO — behavior/display flag |
| `is_visible_on_admin` | `boolean` | Admin visibility | NO — behavior/display flag |

**Note**: All `is_*` columns on attributes are behavior/capability/display flags, not lifecycle. No changes required.

---

### 2.9 `product_attribute_values`

No lifecycle fields. Supporting entity only. No changes needed.

---

### 2.10 `product_attribute_sets`

| Column | Type | Current Purpose | Lifecycle Concern |
|---|---|---|---|
| `is_default` | `boolean` | Default set marker | NO — **designation, kept as boolean** |

---

### 2.11 Pivot tables (`category_product`, `collection_product`, `product_variant_options`, `product_attribute_*`)

No lifecycle fields. No changes needed.

---

## 3. Problems Summary

### P1: `is_*` booleans used as lifecycle state
**Affects**: Variants (`is_enabled`), Categories (`is_visible`), Collections (`is_visible`), Options (`is_visible`), AttributeGroups (`is_visible`)

**Problem**: Booleans record current state with zero audit trail. You cannot answer: "When was this disabled?" or "When was this hidden?"

**Fix priority**: CRITICAL — `is_enabled` on variants becomes `deactivated_at` timestampTz. `is_visible` on catalog entities becomes `visibility` string + `hidden_at` timestampTz.

### P2: Missing transition timestamps on `products`
**Affects**: Products

**Problem**: `published_at` records first activation, but no column records when a product was disabled or archived. Audit gap.

**Fix priority**: HIGH — add `deactivated_at`, `archived_at`.

### P3: No status/lifecycle on supportive catalog entities
**Affects**: Categories, Collections

**Problem**: These entities only have `is_visible` boolean. No way to distinguish "archived" from "hidden" from "active". No transition audit.

**Fix priority**: HIGH — add `status` enum + `hidden_at` + `archived_at`.

### P4: Variants lack lifecycle transition timestamp
**Affects**: Variants

**Problem**: Variants have no transition timestamps beyond `is_enabled`. They cannot be independently audited for when they were disabled.

**Fix priority**: HIGH — add `deactivated_at` timestampTz.

### P5: Options and attribute groups lack visibility transition tracking
**Affects**: Options, AttributeGroups

**Problem**: When `is_visible` changes to false, there's no timestamp recording when that happened.

**Fix priority**: MEDIUM — add `visibility` string + `hidden_at` timestampTz.

---

## 4. Recommended Structure

### 4.1 `products`

```sql
-- Keep
status           STRING   ENUM(draft, active, disabled, archived)
visibility       STRING   ENUM(catalog, search, catalog_search, individual, hidden)
published_at     TIMESTAMPTZ NULL  -- first activation
is_featured      BOOLEAN           -- [KEPT] curation flag

-- Add
deactivated_at   TIMESTAMPTZ NULL  -- set when status → disabled
archived_at      TIMESTAMPTZ NULL  -- set when status → archived
```

**Transition rules** (enforced in UpdateProductStatus Action):
| Transition | Sets |
|---|---|
| Any → Active (first time) | `published_at = now()` |
| Active → Disabled | `deactivated_at = now()`, null archived_at |
| Active → Archived | `archived_at = now()`, null deactivated_at |
| Disabled → Active | null deactivated_at |
| Archived → Draft | null archived_at |
| Draft → Active | `published_at = now()` |

### 4.2 `product_variants`

```sql
-- Keep
is_enabled       BOOLEAN           -- [KEPT] enabled toggle
is_default       BOOLEAN           -- [KEPT] designation

-- Add
deactivated_at   TIMESTAMPTZ NULL  -- set when is_enabled → false
```

**Active check**: `is_enabled = true` (existing pattern). `deactivated_at` provides audit trail.

### 4.3 `product_categories`

```sql
-- Remove
is_visible       (replaced by visibility + status)

-- Keep
is_featured      BOOLEAN           -- [KEPT] curation flag

-- Add
status           STRING   ENUM(active, hidden, archived)  default 'active'
visibility       STRING   VARCHAR(20)  default 'catalog'
hidden_at        TIMESTAMPTZ NULL  -- set when status → hidden
archived_at      TIMESTAMPTZ NULL  -- set when status → archived
```

### 4.4 `product_collections`

```sql
-- Remove
is_visible       (replaced by visibility + status)

-- Keep
is_featured      BOOLEAN           -- [KEPT] curation flag
published_at     TIMESTAMPTZ NULL  -- scheduling window start
unpublished_at   TIMESTAMPTZ NULL  -- scheduling window end

-- Add
status           STRING   ENUM(active, hidden, archived)  default 'active'
visibility       STRING   VARCHAR(20)  default 'catalog'
hidden_at        TIMESTAMPTZ NULL  -- set when status → hidden
archived_at      TIMESTAMPTZ NULL  -- set when status → archived
```

**Semantics**: `status = active` AND within published/unpublished window → publicly visible. `status = hidden` → never visible regardless of scheduling. `status = archived` → soft-deleted.

### 4.5 `product_options`

```sql
-- Remove
is_visible       (replaced by visibility)

-- Add
visibility       STRING   VARCHAR(20)  default 'visible'  -- 'visible' | 'hidden'
hidden_at        TIMESTAMPTZ NULL  -- set when visibility → hidden
```

### 4.6 `product_attribute_groups`

```sql
-- Remove
is_visible       (replaced by visibility)

-- Add
visibility       STRING   VARCHAR(20)  default 'visible'  -- 'visible' | 'hidden'
hidden_at        TIMESTAMPTZ NULL  -- set when visibility → hidden
```

### 4.7 `product_attributes`

```sql
-- Keep all columns as-is (behavior/capability/display flags, not lifecycle)
is_required           BOOLEAN
is_filterable          BOOLEAN
is_searchable          BOOLEAN
is_comparable          BOOLEAN
is_visible_on_front    BOOLEAN
is_visible_on_admin    BOOLEAN
```

No changes required.

### 4.8 `product_attribute_sets`

```sql
-- Keep as designation
is_default    BOOLEAN  -- [KEPT] designation, not lifecycle
```

### 4.9 All other tables (attribute_values, pivots)

No changes required.

---

## 5. Refactoring Plan with Parallel Checklists

### Phase 1: Foundation — Enums & Model Consistency (can be parallelized)

- [x] **1a** Create `Visibility` enum (values depend on entity: `catalog`, `search`, `catalog_search`, `individual`, `hidden` for products; `visible`, `hidden` for options/groups)
- [x] **1b** Create `CatalogStatus` enum (`active`, `hidden`, `archived`) — shared by Categories, Collections
- [x] **1c** Add `ProductStatus::transitionTimestamps()` static method returning transition rules
- [x] **1d** Add `ProductStatus::timestampForTransition(ProductStatus $to): ?string` helper

### Phase 2: Products Table — Add deactivated_at, archived_at

- [x] **2a** Migration: Add `deactivated_at` timestampTz nullable
- [x] **2b** Migration: Add `archived_at` timestampTz nullable
- [x] **2c** Migration: Reindex — add `deactivated_at`/`archived_at` indexes
- [x] **2d** Model: Update `$casts` — add `deactivated_at => datetime`, `archived_at => datetime`
- [x] **2e** Model: Update `activate()` method — still sets `published_at`
- [x] **2f** Model: Add `scopeDisabled()`, `scopeArchived()`
- [x] **2g** Action: Update `UpdateProductStatus` — set/clear `deactivated_at`/`archived_at` on transitions
- [x] **2h** Factory: Update `ProductFactory` as needed

### Phase 3: Variants Table — Add deactivated_at

- [x] **3a** Migration: Add `deactivated_at` timestampTz nullable
- [x] **3b** Migration: Reindex — add `deactivated_at` index
- [x] **3c** Model: Add `deactivated_at` cast
- [x] **3d** Model: Add `booted()` listener to set `deactivated_at` when `is_enabled` becomes false
- [x] **3e** Factory: Update `VariantFactory` as needed

### Phase 4: Categories Table — Add status, visibility, timestamps

- [x] **4a** Migration: Add `status` string column, default `active`
- [x] **4b** Migration: Add `visibility` string column
- [x] **4c** Migration: Add `hidden_at` timestampTz nullable
- [x] **4d** Migration: Add `archived_at` timestampTz nullable
- [x] **4e** Migration: Migrate data — `status = CASE WHEN is_visible THEN 'active' ELSE 'hidden' END`, `hidden_at = updated_at WHERE is_visible = false`
- [x] **4f** Migration: Drop `is_visible` column
- [x] **4g** Migration: Reindex — drop `is_visible` index, add `status` index, `hidden_at` index
- [x] **4h** Model: Add `CatalogStatus` cast, `visibility` string, remove `is_visible` cast
- [x] **4i** Model: Update `scopeVisible()` → `where('status', CatalogStatus::Active)`
- [x] **4j** Model: Update `scopeFeatured()` — keeps using `is_featured = true`
- [x] **4k** Model: Add `scopeHidden()`, `scopeArchived()`
- [x] **4l** Factory: Update `CategoryFactory`

### Phase 5: Collections Table — Add status, visibility, timestamps

- [x] **5a** Migration: Add `status` string column, default `active`
- [x] **5b** Migration: Add `visibility` string column
- [x] **5c** Migration: Add `hidden_at` timestampTz nullable
- [x] **5d** Migration: Add `archived_at` timestampTz nullable
- [x] **5e** Migration: Migrate data (same pattern as categories)
- [x] **5f** Migration: Drop `is_visible` column
- [x] **5g** Migration: Reindex
- [x] **5h** Model: Add `CatalogStatus` cast, `visibility` string, remove `is_visible` cast
- [x] **5i** Model: Update `scopeVisible()`, `scopeFeatured()`, `scopePublished()`, `isPublished()`
- [x] **5j** Model: Scheduling (`published_at`/`unpublished_at`) is NOT touched — remains as-is alongside `status`
- [x] **5k** Factory: Update `CollectionFactory`

### Phase 6: Options Table — Add visibility, hidden_at

- [x] **6a** Migration: Add `visibility` string column, default `visible`
- [x] **6b** Migration: Add `hidden_at` timestampTz nullable
- [x] **6c** Migration: Migrate data — `visibility = CASE WHEN is_visible THEN 'visible' ELSE 'hidden' END`, `hidden_at = updated_at WHERE is_visible = false`
- [x] **6d** Migration: Drop `is_visible` column
- [x] **6e** Model: Add `visibility` string, remove `is_visible` cast
- [x] **6f** Model: Update `scopeVisible()` → `where('visibility', 'visible')`
- [x] **6g** Model: Add `booted()` listener to set `hidden_at` when visibility→hidden
- [x] **6h** Factory: Update `OptionFactory`

### Phase 7: Attribute Groups Table — Add visibility, hidden_at

- [x] **7a** Migration: Add `visibility` string column, default `visible`
- [x] **7b** Migration: Add `hidden_at` timestampTz nullable
- [x] **7c** Migration: Migrate data (same pattern as options)
- [x] **7d** Migration: Drop `is_visible` column
- [x] **7e** Model: Add `visibility` string, remove `is_visible` cast
- [x] **7f** Model: Update `scopeVisible()` → `where('visibility', 'visible')`
- [x] **7g** Model: Add `booted()` listener to set `hidden_at` when visibility→hidden
- [x] **7h** Factory: Update `AttributeGroupFactory`

### Phase 8: Cross-cutting — Filament Resources, Actions, Events, Tests

- [x] **8a** Audit Filament product resources for new `deactivated_at`/`archived_at` columns
- [x] **8b** Audit Filament variant resources for `deactivated_at`
- [x] **8c** Audit Filament category/collection resources for `status`/`visibility`/`hidden_at`/`archived_at`
- [x] **8d** Update `ProductStatusChanged` event — emit transition timestamps
- [x] **8e** Update all factories to use new fields
- [x] **8f** Update all tests to use new fields
- [x] **8g** Grep entire codebase for removed column names → fix

### Phase 9: Final Cleanup & Verification

- [x] **9a** PHPStan: `./vendor/bin/phpstan analyse packages/products/src --level=6`
- [x] **9b** Run product tests: `./vendor/bin/pest --parallel packages/products/tests`
- [x] **9c** Run all tests (if products touches other packages): `./vendor/bin/pest --parallel`
- [x] **9d** Verify no dead references to removed columns
- [x] **9e** Update docs: `packages/products/docs/`

---

## 6. Migration Strategy

### 6.1 Timing & Ordering

All Phase 2-7 migrations are **breaking** (no backward compat). Write them as a single timestamped batch so they apply atomically in one `php artisan migrate`.

**Batch ordering** (within a single migration timestamp):
1. Products (add `deactivated_at`, `archived_at`)
2. Variants (add `deactivated_at`)
3. Categories (add `status`, `visibility`, `hidden_at`, `archived_at`; drop `is_visible`)
4. Collections (add `status`, `visibility`, `hidden_at`, `archived_at`; drop `is_visible`)
5. Options (add `visibility`, `hidden_at`; drop `is_visible`)
6. AttributeGroups (add `visibility`, `hidden_at`; drop `is_visible`)

### 6.2 Data Migration Strategy

Data migration runs inline in the migration `up()` method. Pattern per table:

```php
// 1. Add new columns
Schema::table($table, function (Blueprint $table) {
    $table->string('status')->default('active')->after('...');
    $table->timestampTz('hidden_at')->nullable()->after('...');
});

// 2. Migrate existing data using raw SQL
DB::statement("UPDATE {$table} SET status = CASE WHEN is_visible THEN 'active' ELSE 'hidden' END");
DB::statement("UPDATE {$table} SET hidden_at = updated_at WHERE is_visible = false");

// 3. Drop old columns
Schema::table($table, function (Blueprint $table) {
    $table->dropColumn(['is_visible']);
});
```

### 6.3 Rollback

No `down()` required per monorepo guidelines. Breaking changes are accepted. Roll forward or restore from backup.

---

## 7. Verification Commands

### Pre-migration verification (run before any changes)

```bash
# Find all is_visible references
rg -n "is_visible" packages/products/src packages/products/database packages/products/config

# Find all is_enabled references  
rg -n "is_enabled" packages/products/src packages/products/database packages/products/config

# Check for cross-package references
rg -n "is_featured|is_visible|is_enabled" packages/filament-products/src
```

### Post-migration verification (run after all changes)

```bash
# Verify no is_visible columns remain on tables we migrated
rg -n "is_visible" packages/products/database/migrations/
rg -n "is_visible" packages/products/src/Models/

# Verify new columns exist in migrations
rg -n "status|visibility|deactivated_at|archived_at|hidden_at" packages/products/database/migrations/

# Verify published_at exists on products and collections
rg -n "published_at" packages/products/database/migrations/

# Run PHPStan
./vendor/bin/phpstan analyse packages/products/src --level=6

# Run tests with --parallel
./vendor/bin/pest --parallel packages/products/tests

# Verify no FK constraints slipped in
rg -n "constrained\(|cascadeOnDelete\(" packages/products/database/migrations/
```
