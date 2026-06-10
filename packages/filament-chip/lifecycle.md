# Filament CHIP — Lifecycle

## 1. Installation

```
composer require aiarmada/filament-chip
```

**Requirements**: PHP 8.4+, Laravel 11+ (Octane-safe), Filament v5, `aiarmada/chip`, `aiarmada/commerce-support`.

**Service provider**: `FilamentChipServiceProvider` registers via `spatie/laravel-package-tools`. On registration it binds `FilamentChipPlugin` as a singleton. On boot it calls `FilamentChipMacros::register()` to install Panel/Split/Stack/Fieldset macros.

**Panel registration**: Add `FilamentChipPlugin::class` to the panel's `->plugins([])` array.

**No migrations**: This package owns no database tables. All models come from `aiarmada/chip`.

## 2. Configuration

**File**: `config/filament-chip.php`

| Key | Default | Purpose |
|-----|---------|---------|
| `navigation.group` | `'CHIP Operations'` | Sidebar navigation group label |
| `navigation.badge_color` | `'primary'` | Default badge color |
| `polling_interval` | `'45s'` | Default table/widget polling |
| `tables.created_on_format` | `'Y-m-d H:i:s'` | DateTime display for created_on |
| `tables.amount_precision` | `2` | Decimal precision for MoneyFormatter |
| `default_currency` | `'MYR'` | Fallback currency code |
| `resources.navigation_sort.*` | 10,20,30,40,50,60 | Sort order for Purchases/Payments/Clients/BankAccounts/Payouts/Statements |

**Feature gates**: `filament-chip.features.regulator_mode` — enables ComplianceReportResource, AuditLogResource, FraudReviewResource, RiskRuleResource.

## 3. Entrypoints

### 3.1 Plugin audience toggles

`FilamentChipPlugin` exposes three fluent methods:

| Method | Default | Effect |
|--------|---------|--------|
| `operatorResources(bool)` | `true` | PurchaseResource, ClientResource, PaymentResource, RefundResource, SendInstructionResource, BankAccountResource |
| `regulatorResources(bool)` | `false` | ComplianceReportResource, AuditLogResource, FraudReviewResource, RiskRuleResource (gated by `features.regulator_mode`) |
| `developerResources(bool)` | `false` | PaymentLinkResource, CompanyStatementResource |

### 3.2 Resources (13 total)

| Resource | Model | Pages | Writable? | Export |
|----------|-------|-------|-----------|--------|
| `PurchaseResource` | `Chip\Models\Purchase` | index, view | Read-only | PurchaseExporter |
| `ClientResource` | `Chip\Models\Client` | index, view | Read-only | — |
| `PaymentResource` | `Chip\Models\Payment` | index, view | Read-only | — |
| `RefundResource` | `Chip\Models\Payment` | — (stub) | — | — |
| `BankAccountResource` | `Chip\Models\BankAccount` | index, create, view | Create | — |
| `SendInstructionResource` | `Chip\Models\SendInstruction` | index, create, view | Create | SendInstructionExporter |
| `CompanyStatementResource` | `Chip\Models\CompanyStatement` | index, view | Read-only | — |
| `ComplianceReportResource` | `Chip\Models\Purchase` | — (stub) | — | — |
| `AuditLogResource` | `Chip\Models\Webhook` | — (stub) | — | — |
| `FraudReviewResource` | `Chip\Models\Payment` | — (stub) | — | — |
| `RiskRuleResource` | `Chip\Models\Purchase` | — (stub) | — | — |
| `PaymentLinkResource` | `Chip\Models\Payment` | — (stub) | — | — |

**Resource completeness gap**: RefundResource, ComplianceReportResource, AuditLogResource, FraudReviewResource, RiskRuleResource, and PaymentLinkResource are registered as stub resources with no pages. They appear in navigation but lead nowhere.

### 3.3 Pages

**AnalyticsDashboardPage** (`/chip/analytics`): Livewire component with `period` (7/30/90), `metrics`, and `revenueTrend` properties. Calls `LocalAnalyticsService::getDashboardMetrics()` and `getRevenueTrend()`. Renders Blade view. Navigation sort: 99.

### 3.4 Widgets (11 total)

| Widget | Type | Sort | Scope |
|--------|------|------|-------|
| `ChipStatsWidget` | StatsOverview (4 cols) | 1 | Owner-scoped Purchase queries; falls back to explicit global |
| `RevenueChartWidget` | Chart (line, full width) | 2 | Owner-scoped Purchase queries; falls back to explicit global |
| `PaymentMethodsWidget` | StatsOverview (dynamic) | 3 | Owner-scoped Purchase queries |
| `RecentTransactionsWidget` | Table (full width) | 4 | Owner-scoped Purchase queries; falls back to explicit global |
| `PayoutStatsWidget` | StatsOverview (4 cols) | 10 | Owner-scoped SendInstruction queries |
| `PayoutAmountWidget` | Chart (line) | 11 | Owner-scoped SendInstruction queries |
| `RecentPayoutsWidget` | Table (full width) | 12 | Owner-scoped SendInstruction queries |
| `BankAccountStatusWidget` | Chart (doughnut) | 13 | Owner-scoped BankAccount queries |
| `AccountBalanceWidget` | StatsOverview (3 cols) | 20 | Calls ChipCollectService::getAccountBalance() |
| `AccountTurnoverWidget` | Chart (line) | 21 | Calls ChipCollectService::getAccountTurnover() |
| `TokenStatsWidget` | StatsOverview (3 cols) | 30 | Owner-scoped Purchase recurring_token queries |

**Widget gap**: AccountBalanceWidget and AccountTurnoverWidget call external services directly and are not owner-scoped. They show account-level data that may not be appropriate in multi-tenant contexts where different owners share the same CHIP account.

**Owner context resolution**: Widgets that use `OwnerContext` (ChipStatsWidget, RevenueChartWidget, RecentTransactionsWidget) call `withResolvedOwnerOrExplicitGlobal()` to ensure owner is resolved. If no owner is present and not in explicit global mode, they enter `OwnerContext::withOwner(null, ...)`.

## 4. Read Paths

### 4.1 Resource listing (index)

All list pages extend either `ReadOnlyListRecords` (from commerce-support) or Filament's `ListRecords`.

- `BaseChipResource::getEloquentQuery()` applies `scopeForOwner()` if the model has the method.
- Navigation badges call `getEloquentQuery()->count()` — owner-scoped automatically.
- Table polling uses `config('filament-chip.polling_interval')` (default 45s).
- Default sort: `created_on` or `created_at` descending.

### 4.2 Resource detail (view)

View pages extend `ReadOnlyViewRecord` or Filament's `ViewRecord`. No additional owner check at view level because Eloquent global scopes (via `HasOwner`) are already active.

BankAccountResource and SendInstructionResource view pages include manual owner re-scoping for action handlers:
```php
BankAccount::query()->forOwner()->whereKey($record->getKey())->first();
```

### 4.3 Global search

`PurchaseResource`: searches `reference`, `reference_generated`, `client.email`, `client.full_name`, `purchase.products.name`.
`ClientResource`: searches `email`, `full_name`, `phone`, `legal_name`, `brand_name`, `registration_number`, `tax_number`.
`SendInstructionResource`: searches `reference`, `email`, `description`.

### 4.4 Widget data

Widgets collect data via Eloquent queries on chip models (Purchase, SendInstruction, BankAccount) or service calls (ChipCollectService, ChipSendService). All Eloquent-based widgets apply `forOwner()` scoping.

### 4.5 Analytics page

Uses `LocalAnalyticsService` from the chip package. Metrics loaded on mount and on period change via Livewire.

## 5. Write Paths

### 5.1 Bank Account creation

**Entrypoint**: `CreateBankAccount` page → `BankAccountForm`

**Flow**:
1. Form validates: `name`, `account_number`, `bank_code` (select from 22 Malaysian banks), `reference` (optional), `group_id` (optional), `is_debiting_account` (checkbox), `is_crediting_account` (checkbox, default true).
2. `mutateFormDataBeforeCreate()` calls `ChipSendService::createBankAccount()` with bank_code, account_number, name, reference.
3. On success: success notification with account ID, data merged with returned `id` and `status`. Redirects to index.
4. On failure: danger notification, form halted.

### 5.2 Payout creation

**Entrypoint**: `CreateSendInstruction` page → `SendInstructionForm`

**Flow**:
1. Form validates: `bank_account_id` (required, scoped select showing only active/approved bank accounts for current owner), `amount` (required, numeric, min 0.01, prefix RM), `description` (required), `reference` (required), `email` (required).
2. **Defense-in-depth owner check**: Re-queries selected bank account with `forOwner()->whereIn('status', ['active', 'approved'])`. If not found, danger notification + halt.
3. Converts MYR float amount to cents (`round($amount * 100)`), calls `ChipSendService::createSendInstruction()`.
4. Success/failure notification, redirects to payouts index.

### 5.3 Bank Account verification (action)

Available on BankAccount list row and view page. Requires `status === 'pending'`. Re-scopes record with `forOwner()`. Calls `ChipSendService::updateBankAccount(id, ['status' => 'verifying'])`.

### 5.4 Bank Account deactivation (action)

Available on BankAccount list row and view page. Requires `status` in `['active', 'approved']`. Re-scopes record with `forOwner()`. Calls `ChipSendService::deleteBankAccount(id)`.

### 5.5 Payout cancel (action)

Available on SendInstruction list row and view page. Visible when `state` in `['received', 'queued']` (list) or `['queued', 'received', 'verifying']` (view). Re-scopes record with `forOwner()`. Calls `ChipSendService::cancelSendInstruction(id)`.

### 5.6 Payout webhook resend (action)

Available on SendInstruction list row and view page. Visible when `state` in `['completed', 'processed', 'failed']`. Re-scopes record with `forOwner()`. Calls `ChipSendService::resendSendInstructionWebhook(id)`.

### 5.7 Company Statement download (action)

Available on CompanyStatement list row and view page. Visible when `status` in `['completed', 'ready']`. Calls `ChipCollectService::getCompanyStatement(id)`. Redirects to `$statement->download_url`.

### 5.8 Company Statement cancel (action)

Visible when `status` in `['queued', 'processing']`. Calls `ChipCollectService::cancelCompanyStatement(id)`.

### 5.9 Exports

**PurchaseExporter**: Exports id, reference, status, client_id, purchase.total (from JSON), payment_method, is_test (Yes/No), created_on, checkout_url. Custom completion notification.

**SendInstructionExporter**: Exports id, reference, state, bank_account_id, amount (MYR), email, description, slug, receipt_url, created_at. Custom completion notification.

## 6. Events & Side Effects

### 6.1 Macros registration

On every request (`packageBooted`), `FilamentChipMacros::register()` installs 4 idempotent macros: `Panel::softShadow()`, `Split::glow()`, `Stack::carded()`, `Fieldset::inlineLabelled()`. Macros are guarded by `hasMacro()` checks.

### 6.2 Service calls (side effects outside the package)

All gateway interactions are delegated to the chip package:
- `ChipCollectService::getAccountBalance()` / `getAccountTurnover()` / `getCompanyStatement()` / `cancelCompanyStatement()`
- `ChipSendService::createBankAccount()` / `updateBankAccount()` / `deleteBankAccount()` / `createSendInstruction()` / `cancelSendInstruction()` / `resendSendInstructionWebhook()`
- `LocalAnalyticsService::getDashboardMetrics()` / `getRevenueTrend()`

This package fires no domain events; eventing belongs to the chip package.

### 6.3 Owner context

Widgets (ChipStatsWidget, RevenueChartWidget, RecentTransactionsWidget) that run outside a resolved owner context enter explicit global mode via `OwnerContext::withOwner(null, fn () => ...)`.
