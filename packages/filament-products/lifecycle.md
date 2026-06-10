# Filament Products — Lifecycle

## 1. Overview

`filament-products` is the Filament admin UI layer for product catalog management. It owns **no domain logic, no migrations, no persistence**. All lifecycle state is consumed from the `products` and `pricing` domain packages.

---

## 2. Form Lifecycle Field Inconsistencies

### 2.1 ProductForm

- **Status field**: `Select` from `ProductStatus` enum, default `draft`. OK.
- **Missing actions**: `Activate` bulk action exists but there is no `Archive` or `Discontinue` row/bulk action. The status flow is incomplete — products can only move draft↔active via bulk actions.
- **Dual field model**: Products use both `status` enum AND `visibility` enum, while Categories/Collections use a single `is_visible` boolean. This inconsistency across resources is a Filament form design concern.

### 2.2 CategoryForm

- **`is_visible` Toggle**: Boolean toggle for catalog visibility. No `deactivated_at` timestamp reference.
- Bulk actions provide "Make Visible" / "Make Hidden" — consistent with form.

### 2.3 CollectionForm

- **Scheduling**: Uses `published_at` / `unpublished_at` datetime pair, different from Product which has no scheduling at all. Collection has a richer lifecycle model than Product.
- **Type**: Manual vs Automatic with conditions repeater. Rebuild action visible only for automatic type.

### 2.4 Attribute Visibility

Attributes use four independent booleans (`is_filterable`, `is_searchable`, `is_comparable`, `is_visible_on_front`, `is_visible_in_admin`) — a different visibility model from the rest of the catalog. No lifecycle (start/end) concept exists for attributes.

**Inconsistency**: This is structurally different from Category/Collection which use `is_visible` + `is_featured`.

---

## 3. Table Filter Gaps

### 3.1 Missing lifecycle filters

| Table | Missing Filter |
|---|---|
| `ProductsTable` | No `visibility` filter (only `status` + `type` + `is_featured` + categories) |
| `CategoriesTable` | No `created_at` date-range filter |
| `CollectionsTable` | No `published` date-range filter (only `is_visible` + `is_featured` + `type`) |
| `AttributesTable` | Five individual TernaryFilters for booleans — no grouped lifecycle filter |
| `AttributeGroupsTable` | Only `is_visible` filter — nothing else |
| `AttributeSetsTable` | Only `is_default` filter |

### 3.2 Filter type mismatches

| Table | Field | Current | Concern |
|---|---|---|---|
| `CategoriesTable` | `is_visible` | `TernaryFilter` | Boolean filter — OK while domain uses boolean |
| `CategoriesTable` | `is_featured` | `TernaryFilter` | Same |
| `CollectionsTable` | `is_visible` | `TernaryFilter` | Boolean — if domain refactors to datetime, replace with date filter |
| `CollectionsTable` | `is_featured` | `TernaryFilter` | Same |

---

## 4. Table Column Inconsistencies

### 4.1 Visibility pattern mismatch across resources

| Resource | Visibility Column | Type |
|---|---|---|
| Product | `status` badge + `visibility` badge (hidden by default) | Dual enum |
| Category | `is_visible` icon (boolean) | Single boolean |
| Collection | `is_visible` icon (boolean) | Single boolean |
| Attribute | `is_visible_on_front` + `is_visible_in_admin` icons | Dual boolean |

### 4.2 Default sort inconsistencies

| Table | Default Sort |
|---|---|
| `ProductsTable` | `created_at` desc |
| `CategoriesTable` | `position` |
| `CollectionsTable` | `position` |
| `AttributesTable` | `position` (reorderable) |
| `AttributeGroupsTable` | `position` (reorderable) |

Categories/Collections sort by `position` while Products sort by `created_at`. This is intentional (position vs recency) but worth noting for lifecycle consistency.

---

## 5. Widget Query Issues

### 5.1 ProductStatsWidget

`StatsOverviewWidget` with 4 stats: Total Products, Active Products (by status), Draft Products, Categories count. Polls every 30s. No coverage for `archived` or `discontinued` products.

### 5.2 ProductTypeDistributionWidget

Filters to `status = Active` only. Uses `requires_shipping` to classify physical vs digital. No breakdown by non-active lifecycle states.

### 5.3 CategoryDistributionChart

Top 10 categories by product count, owner-scoped. No time-window filter (e.g., products added in last N days).

### 5.4 TopSellingProductsWidget

Recent 10 active products. Uses "active" filter but "recent" is `latest()`, not a date-filtered query. No configurable time window.

**Owner safety**: All widgets use `withResolvedOwnerOrExplicitGlobal()` pattern. Consistent.

---

## 6. Action Gaps

### 6.1 Product actions

| Action | Where | Gap |
|---|---|---|
| Activate (bulk) | ProductsTable | Exists |
| Set to Draft (bulk) | ProductsTable | Exists |
| Update Price (bulk) | ProductsTable | Exists |
| Change Visibility (bulk) | ProductsTable | Exists |
| Duplicate | Row action | Exists |
| Archive | — | **Missing** — no row or bulk action |
| Discontinue | — | **Missing** — no row or bulk action |

### 6.2 Collection actions

| Action | Where | Gap |
|---|---|---|
| Rebuild | Row action (automatic only) | Exists |
| Publish / Unpublish | — | **Missing** — must edit form to change `published_at`/`unpublished_at` |

### 6.3 Category actions

Bulk "Make Visible" / "Make Hidden" exist. No per-row toggle action — must edit form.

### 6.4 AttributeSet actions

"Set Default" row action exists (only visible when not already default). This is a singleton-pattern action, not a lifecycle action. No activate/deactivate for attribute sets.

---

## 7. Unused Config Keys

| Key | Declared | Read By |
|---|---|---|
| `features.bulk_edit` | config | **Never read** — bulk edit always available |
| `features.import_export` | config | **Never read** — import/export always available |

These dead config keys should be removed or wired to their feature gates.

---

## 8. Verification Commands

```bash
# 1. PHPStan on filament-products
./vendor/bin/phpstan analyse packages/filament-products/src --level=6

# 2. Verify all resources apply owner scoping
rg -n "getEloquentQuery" packages/filament-products/src/Resources/

# 3. Verify lifecycle filter coverage
rg -n "SelectFilter\|TernaryFilter\|Filter::make" packages/filament-products/src/Resources/*/Tables/

# 4. Verify unused config keys
rg -n "bulk_edit\|import_export" packages/filament-products/config/
rg -n "bulk_edit\|import_export" packages/filament-products/src/

# 5. Run filament-products tests
./vendor/bin/pest --parallel packages/filament-products/tests/

# 6. Pint formatting
./vendor/bin/pint packages/filament-products/src --test
```
