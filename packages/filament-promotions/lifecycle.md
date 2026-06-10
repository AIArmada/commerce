# Filament Promotions Lifecycle

## 1. Package Registration & Boot

`FilamentPromotionsServiceProvider` extends `Spatie\LaravelPackageTools\PackageServiceProvider`.

**Registration phase** (`packageRegistered`):
- Binds `FilamentPromotionsPlugin::class` as a singleton in the container.
- Package name: `filament-promotions`.
- Publishes the config file `filament-promotions.php`.

**Boot phase** (`packageBooted`):
- No-op. The Filament panel plugin handles all resource/widget registration at panel boot time.

## 2. Plugin Registration on Panel

`FilamentPromotionsPlugin` implements `Filament\Contracts\Plugin`.

**`getId()`**: Returns `'filament-promotions'`.

**`register(Panel $panel)`** — called when the plugin is registered on a panel:
1. Registers `PromotionResource::class` as a panel resource.
2. Registers `PromotionStatsWidget::class` as a panel widget.

**`boot(Panel $panel)`**: No-op (runtime integration hooks live in the service provider).

The plugin is retrievable via `FilamentPromotionsPlugin::make()` (from container) or `FilamentPromotionsPlugin::get()` (from the panel's plugin registry).

## 3. Resource Lifecycle

`PromotionResource` extends `Filament\Resources\Resource` and models `AIArmada\Promotions\Models\Promotion`.

### Static property defaults
| Property | Value |
|---|---|
| `$navigationIcon` | `Heroicon::OutlinedSparkles` |
| `$navigationLabel` | `'Promotions'` |
| `$modelLabel` | `'Promotion'` |
| `$pluralModelLabel` | `'Promotions'` |
| `$recordTitleAttribute` | `'name'` |

### Authorization gates
All `can*` methods delegate to `FilamentPermission::hasAbility()`:
- `canViewAny()` — `promotion.viewAny`
- `canView()` — `promotion.view`
- `canCreate()` — `promotion.create`
- `canEdit()` — `promotion.update`
- `canDelete()` — `promotion.delete`
- `shouldRegisterNavigation()` — delegates to `canViewAny()`

### Owner scoping
`getEloquentQuery()` applies `PromotionsOwnerScope::applyToOwnedQuery($query)` to enforce multitenancy on every read path.

### Navigation badge
Shows count of active promotions (`is_active = true`) scoped to current owner. Badge color: `success`.

### Navigation group & sort
Read from config:
- `filament-promotions.navigation_group` (default: `'Marketing'`)
- `filament-promotions.resources.navigation_sort.promotions` (default: `10`)

### Pages
| Route | Page class | URL |
|---|---|---|
| `index` | `ListPromotions` | `/` |
| `create` | `CreatePromotion` | `/create` |
| `view` | `ViewPromotion` | `/{record}` |
| `edit` | `EditPromotion` | `/{record}/edit` |

### Relations
- `IssuedVouchersRelationManager` — conditionally registered only when `Promotion::supportsIssuedVoucherTracking()` returns `true`.

### Schema delegates
- `form()` → `PromotionForm::configure($schema)`
- `infolist()` → `PromotionInfolist::configure($schema)`
- `table()` → `PromotionsTable::configure($table)`

## 4. Page Lifecycle

### 4.1 ListPromotions (ListRecords)

**Header widgets** (rendered above the table):
1. `PromotionStatsWidget` (sort: 10)
2. `TopPromotionsUsageChart` (sort: 11)

**Header actions**:
1. `CreateAction::make()` — always present.
2. `IssuePromotionVouchersFromListAction::make()` — conditionally present when `Promotion::supportsIssuedVoucherTracking()`.

### 4.2 CreatePromotion (CreateRecord)

**`mutateFormDataBeforeCreate(array $data): array`** — fires before the record is persisted:
1. If `conditions` is empty array or `null`, sets it to `null` and returns early.
2. If `conditions` is not an array, throws `ValidationException`.
3. Validates conditions via `TargetingEngineInterface::validate($conditions)`.
4. If validation errors exist, throws `ValidationException` with the error messages joined.

**`getRedirectUrl()`**: Returns the resource index URL (redirects to list after creation).

### 4.3 EditPromotion (EditRecord)

**`mutateFormDataBeforeSave(array $data): array`** — fires before the record is updated:
1. Identical validation logic as `CreatePromotion::mutateFormDataBeforeCreate`.

**Header actions** (in order):
1. `IssuePromotionVouchersAction::make()` — conditionally present when `Promotion::supportsIssuedVoucherTracking()`.
2. `ViewAction::make()`
3. `DeleteAction::make()`

**`getRedirectUrl()`**: Returns the resource index URL.

### 4.4 ViewPromotion (ViewRecord)

**Header actions** (in order):
1. `IssuePromotionVouchersAction::make()` — conditionally present when `Promotion::supportsIssuedVoucherTracking()`.
2. `EditAction::make()`
3. `DeleteAction::make()`

### 4.5 IssuedVouchersRelationManager

Read-only relation manager for the `issuedVouchers` relationship on Promotion.

- **Title**: `'Issued Vouchers'`
- **`canViewForRecord()`**: Returns `true` only when the owner record is a `Promotion` and `supportsIssuedVoucherTracking()`.
- **`isReadOnly()`**: Returns `true` (no create/edit/delete actions).
- **Query**: Eager-loads `usages_count` via `withCount('usages')`.
- **Columns**: code (copyable), name, type (badge + color), value (formatted), usages_count, applied_count, status (badge + color by state), expires_at, updated_at.
- **Default sort**: `updated_at` descending.
- **Record URL**: `null` (no click-through).

## 5. Form Lifecycle

`PromotionForm::configure(Schema $schema)` builds the form using `Filament\Schemas\Schema`.

### Sections and fields

| Section | Fields |
|---|---|
| **Basic Information** | `name` (required, max 255, full span), `description` (textarea, 3 rows, full span), `code` (max 50, unique ignoring current record) |
| **Discount Configuration** | `type` (Select from `PromotionType` enum cases, required, non-native), `discount_value` (numeric, required, helper shows MoneyFormatter), `min_purchase_amount` (numeric, nullable, helper shows MoneyFormatter) |
| **Usage Limits** | `usage_limit` (numeric, nullable), `per_customer_limit` (numeric, nullable) — both in a 2-column `Grid` |
| **Scheduling** | `starts_at` (DateTimePicker, optional), `ends_at` (DateTimePicker, optional) — in a 2-column `Grid` |
| **Targeting Conditions** | `conditions` (KeyValue, reorderable, full span) — collapsible, collapsed by default |
| **Options** | `is_active` (Toggle, default true), `is_stackable` (Toggle, default false), `priority` (numeric, default 0) — in a 3-column `Grid` |

### Money formatting
Currency is read from `config('promotions.defaults.currency', 'USD')`. Helper text on `discount_value` and `min_purchase_amount` uses `MoneyFormatter::formatMinor()` for display.

### Validation lifecycle
Field-level validation rules (Filament built-in): `required`, `numeric`, `maxLength`, `unique`.

Page-level validation (in `CreatePromotion` and `EditPromotion`): targeting conditions are validated server-side through `TargetingEngineInterface::validate()` during `mutateFormDataBeforeCreate` / `mutateFormDataBeforeSave`.

## 6. Action Lifecycle

### 6.1 IssuePromotionVouchersAction (in-page, on Edit/View)

Extends `Filament\Actions\Action`. Used on Edit and View pages for a single promotion record.

**`setUp()` configuration**:
- Label: `'Issue Vouchers'`
- Icon: `Heroicon::OutlinedTicket`
- Color: `primary`
- Modal heading: `'Issue vouchers from promotion'`
- Visibility: only when `Promotion::supportsIssuedVoucherTracking()`.
- Authorization: requires both `promotion.update` and `voucher.create` abilities.

**Form fields**:
1. `count` — numeric, min 1, max 100, required, default 1.
2. `code_prefix` — max 40 chars; defaults to `Str::upper(Str::slug($record->code ?? $record->name, '-'))`.

**`action()` execution flow**:
1. `resolvePromotion($record)` — if `PromotionsOwnerScope::isEnabled()`, validates ownership via `OwnerWriteGuard::findOrFailForOwner()` with `includeGlobal: false`.
2. If the promotion is global (`owner_type` and `owner_id` both null), wraps execution in `OwnerContext::withOwner(null, ...)` for explicit global context.
3. Calls `IssueVouchersFromPromotion::run($promotion, $count, $codePrefix)` (domain action from the `promotions` package).
4. On success: sends a success notification with the count of issued vouchers.
5. On `Throwable`: reports the exception and sends a danger notification with the error message.

**Name**: `'issue_vouchers'` (via `getDefaultName()`).

### 6.2 IssuePromotionVouchersFromListAction (from list page)

Extends `Filament\Actions\Action`. Used on the List page header — user selects a promotion from a dropdown.

**`setUp()` configuration**:
- Label: `'Issue Vouchers'`
- Icon: `Heroicon::OutlinedTicket`
- Color: `primary`
- Modal heading: `'Issue vouchers from a promotion'`
- Visibility: only when `Promotion::supportsIssuedVoucherTracking()`.
- Authorization: requires both `promotion.update` and `voucher.create` abilities.

**Form fields**:
1. `promotion_id` — Select, searchable, non-native, required. Options are owner-scoped promotions via `PromotionsOwnerScope::applyToOwnedQuery()`, ordered by name. Labels include the promo code in parentheses when present.
2. `count` — numeric, min 1, max 100, required, default 1.
3. `code_prefix` — max 40 chars, no default prefix (unlike the in-page variant).

**`action()` execution flow**:
1. `resolvePromotion(string $promotionId)` — when owner scoping is enabled, uses `OwnerWriteGuard::findOrFailForOwner()`; otherwise falls back to a plain `find()` with `ModelNotFoundException`.
2. Same global-context wrapping and `IssueVouchersFromPromotion::run()` call as the in-page variant.
3. Same success/error notification pattern.

**Name**: `'issue_vouchers'` (same name — both actions share the default name).

### 6.3 Delete action owner guard (table row + bulk)

Both `DeleteAction` (row) and `DeleteBulkAction` (bulk) in `PromotionsTable` have a `->before()` hook that:
1. Checks if `PromotionsOwnerScope::isEnabled()`.
2. If enabled, validates each record via `OwnerWriteGuard::findOrFailForOwner()` with `includeGlobal: false`.
3. If the record does not belong to the current owner, the guard throws, preventing deletion.

## 7. Widget Lifecycle

### 7.1 PromotionStatsWidget

Extends `Filament\Widgets\StatsOverviewWidget`. Sort: 10.

**`getStats()`** — called on each page render:
1. Retrieves `PromotionPerformanceInsights::overview()` from the container.
2. Resolves reporting currency from `$overview['reporting_currency']` (falls back to `config('promotions.defaults.currency', 'USD')`).
3. Formats revenue/discount values with `MoneyFormatter::formatMinorWithCode()`; falls back to `'Mixed currencies'` when multiple currencies are present.

**Stats displayed**: Total Promotions, Active Promotions (code vs automatic breakdown), Campaign Vouchers (redeemed + active), Orders Influenced, Influenced Revenue, Discount Attributed (code vs automatic).

### 7.2 TopPromotionsUsageChart

Extends `Filament\Widgets\ChartWidget`. Sort: 11. Full column span.

**`getData()`** — called on each page render:
1. First attempts `PromotionPerformanceInsights::topPromotionsByOrders()`.
2. If orders analytics are available (result non-empty), renders a bar chart labeled "Orders Influenced".
3. Falls back to `PromotionPerformanceInsights::topPromotionsByUsage()` with label "Redemptions".

**Chart configuration**: Type `'bar'`, index axis horizontal (`'y'`), legend hidden, x-axis begins at zero.
