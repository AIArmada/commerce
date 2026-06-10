# Filament Customers — Lifecycle UI Audit

## 1. Executive Summary

`filament-customers` is the Filament admin UI layer over the domain `customers` package. When the domain layer refactors lifecycle booleans to `*_at` timestamp columns, every Filament surface that reads or writes those columns must be updated. This audit covers only `filament-customers` surfaces.

---

## 2. Filament Surface Inventory

### 2.1 CustomerForm (`CustomerResource/Schemas/CustomerForm.php`)

| Field | Type | Lifecycle Concern |
|---|---|---|
| `Select::make('status')` | CustomerStatus enum | OK — lifecycle driver |
| `Toggle::make('accepts_marketing')` | boolean | Consent toggle — when flipped, domain model sets `marketing_consented_at`/`marketing_revoked_at`. Form writes to `accepts_marketing` boolean (retained by domain). No change needed. |

### 2.2 CustomersTable (`CustomerResource/Tables/CustomersTable.php`)

| Component | Field | Lifecycle Concern |
|---|---|---|
| `TextColumn::make('status')` + `SelectFilter` | `status` | OK |
| `IconColumn::make('accepts_marketing')` + `TernaryFilter` | `accepts_marketing` | Boolean display — retained by domain, no change needed |
| `TextColumn::make('created_at')` | `created_at` | OK |
| BulkAction `opt_in_marketing` / `opt_out_marketing` | delegates to `$record->optIn/OutMarketing()` | Domain actions set timestamps — no Filament change needed |

### 2.3 SegmentForm (`SegmentResource/Schemas/SegmentForm.php`)

| Field | Type | Problem |
|---|---|---|
| `Toggle::make('is_automatic')` | boolean | NOT lifecycle — structural (segment type). No change. |
| `Toggle::make('is_active')` | boolean | **LIFECYCLE** — domain replaces with `activated_at`/`deactivated_at`. Form must switch to reading/writing these timestamps or a computed status. |

**Changes needed:**
- Replace `is_active` Toggle handling to work with `activated_at`/`deactivated_at`. Options:
  - Keep Toggle but map to domain `activate()`/`deactivate()` methods
  - Replace with `Select::make('status')` if domain adds a `SegmentStatus` enum

### 2.4 SegmentsTable (`SegmentResource/Tables/SegmentsTable.php`)

| Component | Field | Problem |
|---|---|---|
| `IconColumn::make('is_active')->boolean()` | `is_active` | **Must be replaced** when domain drops `is_active` column |
| `TernaryFilter::make('is_active')` | `is_active` | **Must be replaced** — filter on `activated_at`/`deactivated_at` instead |

**Changes needed:**
- Table column: replace `IconColumn` with computed display using `activated_at`/`deactivated_at`
- Filter: replace `TernaryFilter` with date-based filters or query on `activated_at IS NOT NULL AND deactivated_at IS NULL`

### 2.5 AddressesRelationManager

| Surface | Column | Problem |
|---|---|---|
| Form | `Toggle::make('is_default_billing')` | Domain replaces with `default_billing_at` timestamp. **Remove Toggle** from form — default assignment handled by row actions. |
| Form | `Toggle::make('is_default_shipping')` | Same — **Remove Toggle** from form. |
| Table | `IconColumn::make('is_default_billing')->boolean()` | **Replace** with computed icon using `default_billing_at` (non-null = set). |
| Table | `IconColumn::make('is_default_shipping')->boolean()` | Same — **Replace** with computed icon using `default_shipping_at`. |
| Action `set_billing` visibility | `! $record->is_default_billing` | **Replace** with `$record->default_billing_at === null`. |
| Action `set_shipping` visibility | `! $record->is_default_shipping` | **Replace** with `$record->default_shipping_at === null`. |

### 2.6 NotesRelationManager

| Surface | Column | Problem |
|---|---|---|
| Form | `Toggle::make('is_internal')` | Domain replaces with `visibility` string column. **Replace** with `Select::make('visibility')` (options: `internal`, `public`, `staff_only`). |
| Form | `Toggle::make('is_pinned')` | Domain replaces with `pinned_at` timestamp. **Remove** from form (pin via row action). |
| Table | `IconColumn::make('is_pinned')->boolean()` | **Replace** with computed check on `pinned_at`. |
| Table | `IconColumn::make('is_internal')->boolean()` | **Replace** with `TextColumn::make('visibility')->badge()`. |
| Table | `TernaryFilter::make('is_internal')` | **Replace** with `SelectFilter::make('visibility')`. |
| Table | `TernaryFilter::make('is_pinned')` | **Replace** with `Filter::make('pinned')` using `whereNotNull('pinned_at')`. |
| Action `pin` visibility | `! $record->is_pinned` | **Replace** with `$record->pinned_at === null`. |

### 2.7 AddressValidationPage

| Query/Action | Current | Should Be |
|---|---|---|
| Unvalidated addresses query | `->where('is_verified', false)` | `->whereNull('verified_at')` |
| Validate action | `$address->update(['is_verified' => true])` | `$address->update(['verified_at' => now()])` or domain action `$address->verify()` |
| Map output | `$address->is_verified` | `$address->verified_at !== null` |

### 2.8 Widgets

| Widget | Lifecycle Query | Concern |
|---|---|---|
| `CustomerStatsWidget` | `SUM(CASE WHEN status = ?...)`, `SUM(CASE WHEN accepts_marketing = ?...)` | OK — `status` and `accepts_marketing` are both retained by domain |
| `RecentCustomersWidget` | `TextColumn('status')`, `IconColumn('accepts_marketing')` | OK |

---

## 3. Problems Summary

### 3.1 Boolean column references that will break

The domain `customers` package replaces lifecycle booleans with `*_at` timestamps. These Filament surfaces directly reference columns that will be dropped:

| Deprecated Column | Filament Surfaces |
|---|---|
| `addresses.is_default_billing` | AddressesRM: form Toggle, table IconColumn, action visibility |
| `addresses.is_default_shipping` | AddressesRM: form Toggle, table IconColumn, action visibility |
| `addresses.is_verified` | AddressValidationPage: query, update, map output |
| `segments.is_active` | SegmentForm: Toggle. SegmentsTable: IconColumn + TernaryFilter |
| `notes.is_internal` | NotesRM: form Toggle, table IconColumn, TernaryFilter |
| `notes.is_pinned` | NotesRM: form Toggle, table IconColumn, TernaryFilter, action visibility |

### 3.2 Form toggles that should be actions-only

`is_default_billing`, `is_default_shipping`, and `is_pinned` lose their boolean columns. Default assignment and pinning should be set via dedicated row actions, not form toggles. Remove these Toggles from forms.

### 3.3 Direct boolean updates

`AddressValidationPage::validateAddress()` calls `$address->update(['is_verified' => true])`. Must switch to `verified_at` column.

---

## 4. Recommended Filament Changes

### 4.1 AddressesRelationManager — Form

**Remove:** `Toggle::make('is_default_billing')`, `Toggle::make('is_default_shipping')`
**Keep:** all other address fields (label, type, recipient, phone, lines, city, etc.)

### 4.2 AddressesRelationManager — Table

```php
// OLD
IconColumn::make('is_default_billing')->boolean()
// NEW
IconColumn::make('default_billing_at')
    ->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : null)
    ->color('success')
```

Action visibility: `! $record->is_default_billing` → `$record->default_billing_at === null`

### 4.3 NotesRelationManager — Form

```php
// OLD
Toggle::make('is_internal')
Toggle::make('is_pinned')
// NEW
Select::make('visibility')->options(['internal' => 'Internal', 'public' => 'Customer Visible', 'staff_only' => 'Staff Only'])->default('internal')
// is_pinned removed from form
```

### 4.4 NotesRelationManager — Table

```php
// OLD
IconColumn::make('is_pinned')->boolean()
IconColumn::make('is_internal')->boolean()
TernaryFilter::make('is_internal')
TernaryFilter::make('is_pinned')
// NEW
IconColumn::make('pinned_at')->icon(fn ($state) => $state ? 'heroicon-s-star' : null)->color('warning')
TextColumn::make('visibility')->badge()
SelectFilter::make('visibility')
Filter::make('pinned')->query(fn ($q) => $q->whereNotNull('pinned_at'))
```

Action visibility: `! $record->is_pinned` → `$record->pinned_at === null`

### 4.5 SegmentForm

Domain replaces `is_active` with `activated_at`/`deactivated_at`. If domain model retains a computed `is_active` attribute, the Toggle can stay. Otherwise:
```php
// NEW — if domain provides activate()/deactivate() methods
Toggle::make('is_active')
    ->afterStateUpdated(function (Segment $record, bool $state): void {
        $state ? $record->activate() : $record->deactivate();
    })
```

### 4.6 SegmentsTable

```php
// OLD
IconColumn::make('is_active')->boolean()
TernaryFilter::make('is_active')
// NEW
TernaryFilter::make('is_active')
    ->queries(
        true: fn ($q) => $q->whereNotNull('activated_at')->whereNull('deactivated_at'),
        false: fn ($q) => $q->whereNull('activated_at')->orWhereNotNull('deactivated_at'),
    )
```

### 4.7 AddressValidationPage

```php
// Query: ->where('is_verified', false) → ->whereNull('verified_at')
// Update: $address->update(['is_verified' => true]) → $address->update(['verified_at' => now()])
// Output: $address->is_verified → $address->verified_at !== null
```

---

## 5. Verification Commands

```bash
# 1. PHPStan on filament-customers
./vendor/bin/phpstan analyse packages/filament-customers/src --level=6

# 2. Grep for deprecated boolean references (should return zero after refactor)
rg -n "is_default_billing|is_default_shipping" packages/filament-customers/src
rg -n "is_verified" packages/filament-customers/src
rg -n "is_internal|is_pinned" packages/filament-customers/src
rg -n "is_active" packages/filament-customers/src

# 3. Grep for direct boolean updates
rg -n "update\(\[.*=>\s*(true|false)" packages/filament-customers/src

# 4. Verify no lifecycle Toggles remain in forms
rg -n "Toggle::make\('is_" packages/filament-customers/src

# 5. Run filament-customers tests
./vendor/bin/pest --parallel packages/filament-customers/tests

# 6. Cross-package impact check
rg -n "is_default_billing|is_default_shipping|is_verified|is_pinned|is_internal" packages/filament-*/src

# 7. Pint formatting
./vendor/bin/pint packages/filament-customers/src --test
```
