# Filament Pricing — Lifecycle Audit

## 1. Executive Summary

`filament-pricing` is a **UI-only package** with no database migrations. It surfaces lifecycle fields from the `pricing` and `promotions` domain packages. The Filament resources expose `starts_at`/`ends_at` scheduling windows and `is_active`/`is_default` booleans. When the domain refactors booleans to `status`/`designation` string columns, all Filament surfaces must switch from Toggle/IconColumn to Select/BadgeColumn.

---

## 2. Full Inventory by Resource

### 2.1 PriceList Resource (model: `AIArmada\Pricing\Models\PriceList`)

| Surface | Field | Current Component | Gap |
|---|---|---|---|
| Form | `starts_at` | DateTimePicker, "Scheduling" section | OK |
| Form | `ends_at` | DateTimePicker, "Scheduling" section | OK |
| Form | `is_active` | Toggle, "Settings" section, default true | **Must become `Select('status')`** with draft/active/inactive/archived/expired |
| Form | `is_default` | Toggle, "Settings" section | **Must become `Select('designation')`** with standard/default/promotional/vip |
| Infolist | `starts_at` / `ends_at` | TextEntry dateTime, placeholder "Not set" | OK |
| Infolist | `is_active` | IconEntry boolean() | **Must become `TextEntry('status')->badge()`** with status color |
| Infolist | `is_default` | IconEntry boolean() | **Must become `TextEntry('designation')->badge()`** |
| Table | `starts_at` / `ends_at` | TextColumn dateTime, hidden by default | **P3**: hidden by default — inconsistent with PromotionsTable which shows them |
| Table | `is_active` | IconColumn boolean() | **Must become `TextColumn('status')->badge()`** |
| Table | `is_default` | IconColumn boolean() | **Must become `TextColumn('designation')->badge()`** |
| Table filter | `is_active` | TernaryFilter "Active" | **Must become `SelectFilter('status')`** |
| Table filter | `is_default` | TernaryFilter "Default" | **Must become `SelectFilter('designation')`** |

### 2.2 Promotion Resource (model: `AIArmada\Promotions\Models\Promotion`)

| Surface | Field | Current Component | Gap |
|---|---|---|---|
| Form | `starts_at` / `ends_at` | DateTimePicker, "Scheduling" section | OK |
| Form | `is_active` | Toggle, "Settings" section, default true | **Must become `Select('status')`** with draft/active/deactivated/expired |
| Infolist | `starts_at` / `ends_at` | TextEntry dateTime, placeholder "Not set" | OK |
| Infolist | `is_active` | IconEntry boolean() | **Must become `TextEntry('status')->badge()`** |
| Table | `starts_at` | TextColumn dateTime, placeholder "Always" | OK (shown by default) |
| Table | `ends_at` | TextColumn dateTime, placeholder "Never" | OK |
| Table | `is_active` | IconColumn boolean() | **Must become `TextColumn('status')->badge()`** |
| Table filter | `is_active` | TernaryFilter "Active" | **Must become `SelectFilter('status')`** |

### 2.3 Prices Relation Manager (model: `AIArmada\Pricing\Models\Price`)

| Surface | Field | Current Component | Gap |
|---|---|---|---|
| Form | `starts_at` / `ends_at` | DateTimePicker (bare, no section wrapper) | **P6**: no "Scheduling" section — inconsistent with PriceList/Promotion forms |
| Table | `starts_at` | TextColumn dateTime, placeholder "Always" | OK |
| Table | `ends_at` | TextColumn dateTime, placeholder "Never" | OK |

No `status` column on Price model currently. When domain adds one, expose in form and table.

### 2.4 Tiers Relation Manager (model: `AIArmada\Pricing\Models\PriceTier`)

| Surface | Field | Current | Gap |
|---|---|---|---|
| (all) | (none) | — | **P7**: no lifecycle fields exposed. When domain adds `status`/`starts_at`/`ends_at` to `price_tiers`, expose them. |

### 2.5 Non-Resource Surfaces

| Surface | Lifecycle Relevance |
|---|---|
| `ManagePricingSettings` | No entity lifecycle — system-wide config |
| `PriceSimulator` | Uses `effective_date` parameter for simulation — not entity lifecycle |
| `PricingStatsWidget` | Calls `PricingOwnerScope::applyToOwnedQuery()` — verify unchanged when domain switches `is_active` to `status` |

---

## 3. Problems Summary

| # | Resource | Severity | Problem |
|---|---|---|---|
| P1 | PriceList (all surfaces) | **High** | `is_active` boolean → `status` Select/TextColumn (draft/active/inactive/archived/expired) |
| P2 | PriceList (all surfaces) | **High** | `is_default` boolean → `designation` Select/TextColumn (null/standard, default, promotional, vip) |
| P3 | PriceList table | **Medium** | `starts_at`/`ends_at` hidden by default — inconsistent with PromotionsTable which shows them. No placeholder for null values. |
| P4 | Promotion (all surfaces) | **High** | `is_active` boolean → `status` Select/TextColumn (draft/active/deactivated/expired) |
| P5 | Table placeholders | **Low** | Inconsistent null display: PriceList table has no placeholders; PromotionsTable uses "Always"/"Never"; PricesRM uses "Always"/"Never"; Infolists use "Not set". Standardize. |
| P6 | Prices RM form | **Low** | `starts_at`/`ends_at` are bare fields — wrap in `Section::make('Scheduling')` matching PriceList/Promotion forms |
| P7 | Tiers RM | **Medium** | No lifecycle fields exposed. When domain adds columns, expose `status`/`starts_at`/`ends_at` |
| P8 | PricingStatsWidget | **Medium** | May need updates when domain switches `is_active` → `status` column in scope methods |

---

## 4. Recommended Filament Changes

### 4.1 PriceList Resource

**Form:** Replace `Toggle('is_active')` → `Select('status')` (draft/active/inactive/archived/expired, default draft). Replace `Toggle('is_default')` → `Select('designation')` (null/standard, default, promotional, vip).

**Table:** Replace `IconColumn('is_active')->boolean()` → `TextColumn('status')->badge()`. Replace `TernaryFilter('is_active')` → `SelectFilter('status')`. Same for `is_default` → `designation`. Remove `toggleable(isToggledHiddenByDefault: true)` from `starts_at`/`ends_at`. Add `placeholder('Always')`/`placeholder('Never')`.

### 4.2 Promotion Resource

**Form:** Replace `Toggle('is_active')` → `Select('status')` (draft/active/deactivated/expired, default draft).

**Table:** Replace `IconColumn('is_active')->boolean()` → `TextColumn('status')->badge()`. Replace `TernaryFilter('is_active')` → `SelectFilter('status')`.

### 4.3 Prices RM Form

Wrap `starts_at`/`ends_at` in `Section::make('Scheduling')->schema([...])->columns(2)`. Add helper text: "Leave empty for no start/end date".

### 4.4 Placeholder Standardization

| Context | `starts_at` null | `ends_at` null |
|---|---|---|
| Forms | `helperText('Leave empty for no start date')` | `helperText('Leave empty for no end date')` |
| Tables | `placeholder('Always')` | `placeholder('Never')` |
| Infolists | `placeholder('Not set')` | `placeholder('Not set')` |

### 4.5 Status Color Map

| Status | Color |
|---|---|
| `draft` | `gray` |
| `active` | `success` |
| `inactive` | `danger` |
| `deactivated` | `danger` |
| `archived` | `warning` |
| `expired` | `info` |

### 4.6 Designation Color Map (PriceList only)

| Designation | Color |
|---|---|
| `null` (standard) | `gray` |
| `default` | `success` |
| `promotional` | `warning` |
| `vip` | `info` |

---

## 5. Deprecated Resource Mirroring

`PromotionResource` in this package delegates to `filament-promotions` when installed. Changes to `PromotionForm`/`PromotionsTable` must be mirrored in both packages.

---

## 6. Verification Commands

```bash
# 1. PHPStan on filament-pricing
./vendor/bin/phpstan analyse packages/filament-pricing/src --level=6

# 2. Grep for lingering boolean lifecycle references
rg -n -- "is_active|is_default" packages/filament-pricing/src

# 3. Verify status/designation is used instead
rg -n -- "\->make\('status'\)|\->make\('designation'\)" packages/filament-pricing/src

# 4. Verify placeholder consistency
rg -n -- "placeholder\('(Always|Never|Not set)'\)" packages/filament-pricing/src

# 5. Verify no boolean() icon/text columns remain
rg -n -- "boolean\(\)" packages/filament-pricing/src

# 6. Run filament-pricing tests
./vendor/bin/pest --parallel packages/filament-pricing/tests/

# 7. Pint formatting
./vendor/bin/pint packages/filament-pricing/src --test

# 8. Check filament-promotions mirror consistency
diff <(rg -n "is_active|status" packages/filament-pricing/src/Resources/PromotionResource) \
     <(rg -n "is_active|status" packages/filament-promotions/src/Resources/PromotionResource 2>/dev/null) || echo "Mismatch or filament-promotions not found"
```
