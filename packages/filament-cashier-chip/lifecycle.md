# Filament Cashier CHIP — Package Lifecycle

## 1. Installation

### 1.1 Composer Requirement

```bash
composer require aiarmada/filament-cashier-chip
```

The package depends on `spatie/laravel-package-tools` for service-provider auto-discovery. No additional Laravel provider registration is needed; the provider is auto-discovered via `composer.json`.

### 1.2 Prerequisites

- `aiarmada/cashier-chip` must be installed and configured (provides `Cashier`, `Subscription`, `Purchase` models).
- `aiarmada/commerce-support` must be installed (provides `OwnerContext`, `OwnerQuery`, `OwnerScope`, `ConnectionDriver`).
- Filament v5 with a registered panel.

### 1.3 Panel Registration

Add the plugin to any admin panel in your panel provider:

```php
use AIArmada\FilamentCashierChip\FilamentCashierChipPlugin;

$panel->plugin(
    FilamentCashierChipPlugin::make()
        ->subscriptions()
        ->customers()
        ->invoices()
        ->dashboardWidgets()
        ->billingDashboard()
        ->billingPortal()
);
```

All feature methods default to `true`. Call `->subscriptions(false)` to disable a feature entirely (overrides config).

### 1.4 Billing Portal Panel

Register the standalone customer-facing billing portal:

```php
// In config/app.php providers or bootstrap/app.php
AIArmada\FilamentCashierChip\CustomerPortal\BillingPanelProvider::class,
```

The billing panel is auto-registered with its own `BillingPanelProvider` and isolated from the admin panel.

> **Deprecated**: `AIArmada\FilamentCashierChip\BillingPanelProvider` is an alias extending `CustomerPortal\BillingPanelProvider`. Use the namespaced class instead.

---

## 2. Service Provider Boot

### 2.1 Provider Class

`FilamentCashierChipServiceProvider` extends `PackageServiceProvider` (`spatie/laravel-package-tools`).

**`configurePackage()`** — registers:
- Package name: `filament-cashier-chip`
- Config file: `config/filament-cashier-chip.php` (published via `hasConfigFile()`)
- Views: from `resources/views` (registered via `hasViews()`)
- Translations: from `lang/` (registered via `hasTranslations()`)

**`packageRegistered()`** — binds:
- `FilamentCashierChipPlugin::class` as a singleton in the container. This ensures `FilamentCashierChipPlugin::make()` resolves the same instance.

### 2.2 Config Publishing

```bash
php artisan vendor:publish --tag=filament-cashier-chip-config
```

Views and translations are published via the standard `spatie/laravel-package-tools` tags (`filament-cashier-chip-views`, `filament-cashier-chip-translations`).

---

## 3. Plugin Lifecycle (`FilamentCashierChipPlugin`)

### 3.1 Plugin Identity

- **ID**: `filament-cashier-chip`
- Implements `Filament\Contracts\Plugin`
- Resolved via `app(self::class)` (singleton binding from service provider)

### 3.2 Feature Flags (internal state)

| Property | Default | Method | Meaning |
|---|---|---|---|
| `hasSubscriptions` | `true` | `subscriptions(bool)` | Register `SubscriptionResource` |
| `hasCustomers` | `true` | `customers(bool)` | Register `CustomerResource` |
| `hasInvoices` | `true` | `invoices(bool)` | Register `InvoiceResource` |
| `hasDashboardWidgets` | `true` | `dashboardWidgets(bool)` | Register dashboard stat/chart widgets |
| `hasBillingDashboard` | `true` | `billingDashboard(bool)` / `dashboard(bool)` | Register `BillingDashboard` page on admin panel |
| `hasBillingPortal` | `true` | `billingPortal(bool)` | Register billing portal pages on the billing panel |

Note: `dashboard()` is an alias for `billingDashboard()` (API parity with `filament-cashier`).

### 3.3 `register(Panel $panel)` — Main Wiring

Called by Filament when the plugin is registered on a panel.

**Step 1 — Determine panel identity**:
```php
$hasUnifiedCashier = $panel->hasPlugin('filament-cashier');
$billingPanelId = config('filament-cashier-chip.billing.panel_id', 'billing');
$isBillingPanel = $panel->getId() === $billingPanelId;
```

**Step 2 — Resource registration** (each gated by plugin flag AND config key):
- `SubscriptionResource` — added if `hasSubscriptions && config('features.subscriptions') && !$hasUnifiedCashier`
- `CustomerResource` — added if `hasCustomers && config('features.customers')`
- `InvoiceResource` — added if `hasInvoices && config('features.invoices') && !$hasUnifiedCashier`

When `filament-cashier` (unified) is present on the same panel, `SubscriptionResource` and `InvoiceResource` are suppressed to avoid duplication.

**Step 3 — Dashboard widget registration** (gated by plugin flag AND config keys):
- `MRRWidget` (sort 1)
- `ActiveSubscribersWidget` (sort 2)
- `ChurnRateWidget` (sort 3)
- `RevenueChartWidget` (sort 4)
- `SubscriptionDistributionWidget` (sort 5)
- `TrialConversionsWidget` (sort 6)
- `AttentionRequiredWidget` (sort 7)

Each widget is individually togglable via `config('features.dashboard.widgets.<name>')`.

**Step 4 — Dashboard page registration**:
- `BillingDashboard` — added if `hasBillingDashboard && !$hasUnifiedCashier`

**Step 5 — Billing portal page registration** (only on the billing panel):
- `Subscriptions` page — if `hasBillingPortal && $isBillingPanel && !$hasUnifiedCashier && config('billing.features.subscriptions')`
- `PaymentMethods` page — if `hasBillingPortal && $isBillingPanel && !$hasUnifiedCashier && config('billing.features.payment_methods')`
- `Invoices` page — if `hasBillingPortal && $isBillingPanel && !$hasUnifiedCashier && config('billing.features.invoices')`

**Step 6 — Apply to panel**:
```php
$panel->resources($resources)->widgets($widgets)->pages($pages);
```

### 3.4 `boot(Panel $panel)` — Post-Registration

Currently empty — a hook point for future initialization (e.g., registering navigation groups, extending panels).

---

## 4. Resource Lifecycle

All three resources (`SubscriptionResource`, `CustomerResource`, `InvoiceResource`) extend `BaseCashierChipResource`, which extends `Filament\Resources\Resource`.

### 4.1 BaseCashierChipResource — Shared Behavior

**Navigation**:
- `getNavigationGroup()` → `config('filament-cashier-chip.navigation.group')` (default: `'Billing'`)
- `getNavigationSort()` → `config('filament-cashier-chip.resources.navigation_sort.{key}')` where key is `subscriptions` (10), `customers` (20), `invoices` (30)
- `getNavigationBadge()` → count of records via `getEloquentQuery()->count()`
- `getNavigationBadgeColor()` → `config('filament-cashier-chip.navigation.badge_color')` (default: `'success'`)

**Owner Scoping** (`getEloquentQuery()`):
1. If `config('cashier-chip.features.owner.enabled')` is `false`, return parent query unchanged.
2. Otherwise, resolve current owner via `OwnerContext::resolve()`.
3. Read `include_global` from `config('cashier-chip.features.owner.include_global')`.
4. If the model implements `ownerScopeConfig()`, read custom owner column names from the config object.
5. Remove the default `OwnerScope` global scope via `->withoutGlobalScope(OwnerScope::class)`.
6. Apply `OwnerQuery::applyToEloquentBuilder($query, $owner, $includeGlobal, $ownerTypeColumn, $ownerIdColumn)`.

This ensures every read path is owner-scoped. Filament tenancy is supplemented — not relied upon — for authorization.

**Helpers**:
- `pollingInterval()` → `config('filament-cashier-chip.tables.polling_interval')` (default: `'45s'`)
- `formatAmount(int $amount, ?string $currency)` → currency + minor-unit formatting using `config('cashier-chip.currency')` and `config('filament-cashier-chip.tables.amount_precision')`

### 4.2 SubscriptionResource

- **Model**: `Cashier::$subscriptionModel` (resolved at runtime from cashier-chip)
- **Icon**: `Heroicon::OutlinedCreditCard`
- **Record title**: `type`

| Page | Route | Class |
|---|---|---|
| index | `/` | `ListSubscriptions` |
| view | `/{record}` | `ViewSubscription` |

**Relations**: `SubscriptionItemsRelationManager` (relationship: `items`)

**Globally searchable**: `type`, `chip_id`, `chip_price`

**Table** (`SubscriptionTable`): Uses `FormatsSubscriptionStatus` trait. Columns: type, customer.name, chip_price, quantity, chip_status (badge with color), on_trial (icon), trial_ends_at, next_billing_at, ends_at (color-coded), billing_interval (badge), created_at. Filters: chip_status (SelectFilter), on_trial (TernaryFilter), canceled (TernaryFilter), on_grace_period, past_due. Polls at configured interval.

**Infolist** (`SubscriptionInfolist`): Sections — Subscription Overview, Plan Details, Customer, Billing Schedule, Discount (collapsible, conditionally visible), Timestamps (collapsed).

### 4.3 CustomerResource

- **Model**: `Cashier::$customerModel` (resolved at runtime from cashier-chip)
- **Icon**: `Heroicon::OutlinedUsers`
- **Record title**: `name`
- **Slug**: `chip-customers` (overridden to avoid collision)

| Page | Route | Class |
|---|---|---|
| index | `/` | `ListCustomers` |
| view | `/{record}` | `ViewCustomer` |

**Relations** (conditionally registered):
- `SubscriptionsRelationManager` — if model has `subscriptions` or `chipSubscriptions` method
- `PaymentMethodsRelationManager` — if `config('features.payment_methods')` is true

**Globally searchable**: `name`, `email`

**Table** (`CustomerTable`): Columns: name, email, chip_customer_id (toggleable), has_chip_customer_link (icon), default_payment_method (badge with last four), subscriptions_count (badge), on_trial (icon), trial_ends_at (toggleable, with sort support via schema introspection), created_at (toggleable). Filters: has_chip_customer_link (TernaryFilter on `chipCustomerLink`), has_payment_method (TernaryFilter on `storedPaymentMethods`), has_subscriptions (Toggle), on_trial (Toggle with `trial_ends_at` schema check).

### 4.4 InvoiceResource

- **Model**: `AIArmada\Chip\Models\Purchase` (fixed — not resolved from cashier-chip)
- **Icon**: `Heroicon::OutlinedDocumentText`
- **Record title**: `reference`

| Page | Route | Class |
|---|---|---|
| index | `/` | `ListInvoices` |
| view | `/{record}` | `ViewInvoice` |

**Globally searchable**: `reference`, `reference_generated`, `client->email`, `client->full_name`

**Table** (`InvoiceTable`): Modifies query with `->orderBy('created_on', 'desc')`. Columns: reference, client.full_name, client.email (toggleable), formatted_total (badge), status (badge with Purchase model helpers), is_paid (icon), created_on, due (toggleable), is_test (toggleable). Filters: status (SelectFilter with all Chip statuses), paid (Toggle), unpaid (Toggle), is_test (Toggle), high_value (Toggle with multi-driver JSON column query using `ConnectionDriver`).

**Infolist** (`InvoiceInfolist`): Sections — Invoice Summary, Customer Details (Contact + Billing Address), Amount Details (subtotal, discount, tax, total from `purchase` JSON), Line Items (RepeatableEntry from `purchase.products`), Payment Information (conditionally visible when `paid`), Checkout (conditionally visible when unpaid), Timestamps (collapsed).

---

## 5. Page Lifecycle (Actions & Operations)

### 5.1 ListSubscriptions

**Header Actions**:
- **Bulk Pause** — `Subscription::query()->where('chip_status', ACTIVE)->update(['chip_status' => PAUSED])`
- **Bulk Resume** — `Subscription::query()->where('chip_status', PAUSED)->update(['chip_status' => ACTIVE])`

### 5.2 ViewSubscription

**Header Actions** (grouped in ActionGroup "Subscription Actions"):
- **Cancel at Period End** — `$subscription->cancel()`. Visible when `!$record->canceled()`. Refreshes `ends_at`, `chip_status`.
- **Cancel Immediately** — `$subscription->cancelNow()`. Visible when `!$record->ended()`. Refreshes `ends_at`, `chip_status`.
- **Resume** — `$subscription->resume()`. Visible when `$record->onGracePeriod()`. Refreshes `ends_at`, `chip_status`.
- **Pause** — `$subscription->pause()`. Visible when `!paused() && active()`. Refreshes `chip_status`.
- **Unpause** — `$subscription->unpause()`. Visible when `paused()`. Refreshes `chip_status`.

**Standalone Header Actions**:
- **Extend Trial** — DateTimePicker form for `trial_ends_at`, calls `$subscription->extendTrial(CarbonImmutable)`. Visible when `onTrial() || trial_ends_at === null`.
- **End Trial Now** — `$subscription->endTrial()`. Visible when `onTrial()`. Refreshes `trial_ends_at`, `chip_status`.
- **Swap Plan** — TextInput form for Chip price ID, calls `$subscription->swap($price)`. Visible when `active() || onTrial()`.
- **Update Quantity** — TextInput form for numeric quantity, calls `$subscription->updateQuantity()`. Visible when `$record->hasSinglePrice()`.
- **Sync Status** — `$subscription->syncChipStatus()`. Always visible. Refreshes `chip_status`.

### 5.3 ListCustomers

**Header Actions**:
- **Sync All to Chip** — Iterates all customers without `chipCustomerLink`, calls `createAsChipCustomer()`. Reports synced/failed count.

### 5.4 ViewCustomer

**Header Actions** (grouped in ActionGroup "Customer Actions"):
- **Create in Chip** — `$record->createAsChipCustomer()`, calls `fresh()`. Visible when not linked.
- **Sync to Chip** — `$record->syncChipCustomerDetails()`, calls `fresh()`. Visible when linked.
- **Refresh Payment Method** — `$record->updateDefaultPaymentMethodFromChip()`, calls `fresh()`. Visible when linked.

**Standalone Header Actions**:
- **Add Payment Method** — `$record->setupPaymentMethodUrl()`. Sends persistent notification with URL. Visible when linked.
- **View in Chip** — External URL to `https://app.chip-in.asia/clients/{chip_id}`. Visible when linked.

### 5.5 ListInvoices

**Header Actions**:
- **Export CSV** — placeholder (action empty)
- **View Reports** — links to `filament.{panelId}.pages.cashier-chip-dashboard` route (BillingDashboard)

### 5.6 ViewInvoice

**Header Actions**:
- **Download PDF** — placeholder (shows info notification). Visible when `status === 'paid'`.
- **Send Invoice** — placeholder (shows success notification). Visible when `client.email` is not empty.
- **View Checkout** — external URL to `$record->checkout_url`. Visible when `checkout_url` is not empty.
- **Copy Checkout URL** — placeholder. Visible when `checkout_url` is not empty and status is not `paid`.
- **Mark as Paid** — Sets `status = 'paid'` and `paid_on = now()->getTimestamp()`, calls `save()`. Visible when `status !== 'paid'`. Refreshes `status`, `paid_on`.

### 5.7 Billing Portal Pages (CustomerPortal)

All portal pages (`Subscriptions`, `PaymentMethods`, `Invoices`, `BillingDashboard`) use `InteractsWithBillable` trait.

#### Billable Resolution (`InteractsWithBillable::getBillable()`)

1. Gets authenticated user from `filament()->auth()->user()`
2. If `config('billing.billable_model')` is set and user is an instance of that class → returns user
3. If user has `currentTeam` attribute and it's a Model → returns team
4. Fallback → returns user

#### BillingDashboard (Customer Portal)

- Header widgets (4 columns): MRR, ActiveSubscribers, ChurnRate, AttentionRequired (gated by config)
- Footer widgets (responsive 1/2/3 columns): RevenueChart, SubscriptionDistribution, TrialConversions (gated by config)

#### Subscriptions (Customer Portal)

- `getViewData()`: Provides billable, active subscriptions (filtered by `getActiveStatuses()`: ACTIVE, TRIALING, PAST_DUE), and cancelled subscriptions (via `onGracePeriod()` scope)
- **cancelSubscription()**: Finds subscription by ID through `$billable->subscriptions()`, calls `$subscription->cancel()`
- **resumeSubscription()**: Finds subscription by ID, calls `$subscription->resume()`
- **formatAmount()**: Delegates to `Cashier::formatAmount()` if available, otherwise manual formatting

#### PaymentMethods (Customer Portal)

- **Header Action**: "Add Payment Method" — generates setup URL from `$billable->setupPaymentMethodUrl()`
- **setAsDefault()**: Calls `$billable->updateDefaultPaymentMethod($id)`
- **deletePaymentMethod()**: Calls `$billable->deletePaymentMethod($id)`
- **formatCardBrand()**: Maps lowercase brand to display name (Visa, Mastercard, etc.)
- `getViewData()` provides billable, payment methods, and default payment method

#### Invoices (Customer Portal)

- **downloadInvoice()**: Calls `$billable->findInvoice($id)->download([vendor, product])`
- **formatInvoiceStatus()**: Maps status to translated labels
- **getStatusColor()**: Maps status (paid → success, open → warning, void/uncollectible → danger)
- `getViewData()` provides billable and `$billable->invoices()`

---

## 6. Widget Lifecycle

### 6.1 Data Resolution

All widgets use the `InteractsWithCashierChipData` trait, which provides:
- `subscriptionModel()` → `Cashier::$subscriptionModel` (class-string resolution)
- `formatCurrency(int $amount)` → currency code + minor-unit to major-unit with configurable precision
- `normalizeToMonthly(int $amount, string $interval, int $count)` → converts any interval to monthly equivalent using multipliers (day: 30/count, week: 4.33/count, month: 1/count, year: 1/(12*count))
- `currency()` → `config('cashier-chip.currency', 'MYR')`

### 6.2 Stats Widgets (StatsOverviewWidget)

All stats widgets share a `getColumns()` returning `1` (except `TrialConversionsWidget` which returns `2`).

| Widget | Sort | Computed Stat | Trend |
|---|---|---|---|
| **MRRWidget** | 1 | Sum of (items amount × quantity) for all active subs, normalized to monthly, minus coupon discounts | Percent change vs previous month MRR (subs created before last month) |
| **ActiveSubscribersWidget** | 2 | Active count + Trialing count (total) | Diff vs previous month active count |
| **ChurnRateWidget** | 3 | (ended in current month) / (active at start of current month) × 100 | Diff vs previous month churn rate |
| **AttentionRequiredWidget** | 7 | Sum of: trials ending in 3 days + past_due + grace period ending in 3 days + incomplete + unpaid | Color-coded by total (0=success, ≤5=warning, >5=danger) |
| **TrialConversionsWidget** (2 columns) | 6 | Stat 1: (converted trials in current month) / (trials ended in current month) × 100. Stat 2: count of currently trialing subs | Trend: diff vs previous month conversion rate |

### 6.3 Chart Widgets (ChartWidget)

| Widget | Sort | Type | Span | Poll | Data |
|---|---|---|---|---|---|
| **RevenueChartWidget** | 4 | `line` | `full` | `120s` | MRR + New Revenue over last 12 months, labels as "M Y" format. Y-axis currency callback. |
| **SubscriptionDistributionWidget** | 5 | `doughnut` | `1` | `120s` | Count per status (Active, Trialing, Canceled, Past Due, Paused, Incomplete). legend positioned at bottom. |

### 6.4 Widget Visibility on BillingDashboard

The `BillingDashboard` page independently re-checks the same config keys for header and footer widgets. If `config('features.dashboard_widgets')` is false, both header and footer widgets return empty arrays.

---

## 7. Runtime Data Flow

### 7.1 Query Paths

1. **Admin resource list/view** → `BaseCashierChipResource::getEloquentQuery()`
   - Strips `OwnerScope` global scope
   - Applies `OwnerQuery::applyToEloquentBuilder()` with resolved owner from `OwnerContext`
   - Supports custom owner columns via `ownerScopeConfig()` on the model
2. **Table filters** → applied on top of the already-scoped query
3. **Widget queries** → direct `subscriptionModel()::query()->...` calls (bypass resource query scoping; widgets operate on global subscription data for aggregate metrics)
4. **Billing portal pages** → access data through `InteractsWithBillable::getBillable()` which resolves the authenticated user/team, then calls methods like `subscriptions()`, `invoices()`, `paymentMethods()` directly on the billable model

### 7.2 Write Paths (Admin Actions)

All mutations go through the model's own methods (from `cashier-chip`):

| Operation | Method Called | Resource |
|---|---|---|
| Cancel subscription | `Subscription::cancel()` / `cancelNow()` | SubscriptionResource |
| Resume subscription | `Subscription::resume()` | SubscriptionResource |
| Pause / Unpause subscription | `Subscription::pause()` / `unpause()` | SubscriptionResource |
| Extend / End trial | `Subscription::extendTrial()` / `endTrial()` | SubscriptionResource |
| Swap plan | `Subscription::swap($price)` | SubscriptionResource |
| Update quantity | `Subscription::updateQuantity($qty)` | SubscriptionResource |
| Sync status | `Subscription::syncChipStatus()` | SubscriptionResource |
| Create Chip customer | `$model->createAsChipCustomer()` | CustomerResource |
| Sync customer | `$model->syncChipCustomerDetails()` | CustomerResource |
| Refresh payment method | `$model->updateDefaultPaymentMethodFromChip()` | CustomerResource |
| Mark invoice paid | Direct `$record->status = 'paid'; $record->save()` | InvoiceResource |
| Bulk pause/resume | Direct `::query()->update(['chip_status' => ...])` | ListSubscriptions |
| Sync all to Chip | Iterates + `createAsChipCustomer()` per record | ListCustomers |
| Subscription item increment | `SubscriptionItem::incrementQuantity()` | SubscriptionItemsRelationManager |
| Subscription item decrement | `SubscriptionItem::decrementQuantity()` | SubscriptionItemsRelationManager |
| Subscription item swap | `SubscriptionItem::swap($price, $options)` | SubscriptionItemsRelationManager |

### 7.3 Write Paths (Billing Portal — Customer-Initiated)

| Operation | Method Called | Guard |
|---|---|---|
| Cancel subscription | `$billable->subscriptions()->find($id)->cancel()` | Owns subscription? |
| Resume subscription | `$billable->subscriptions()->find($id)->resume()` | Owns subscription? |
| Set default payment method | `$billable->updateDefaultPaymentMethod($id)` | method_exists check |
| Delete payment method | `$billable->deletePaymentMethod($id)` | method_exists check |
| Download invoice | `$billable->findInvoice($id)->download([...])` | Owns invoice? |

### 7.4 View Rendering

- **Resource pages** use Filament's standard infolist/tables rendering — no custom views.
- **Billing portal pages** use Blade views:
  - `filament-cashier-chip::pages.billing-dashboard`
  - `filament-cashier-chip::pages.subscriptions`
  - `filament-cashier-chip::pages.payment-methods`
  - `filament-cashier-chip::pages.invoices`
- Views receive data via `getViewData()` which passes billable, subscriptions, payment methods, and invoices to the Blade template.

### 7.5 Translation Flow

All user-facing strings use Laravel's `__()` helper with the `filament-cashier-chip::filament-cashier-chip.*` namespace:
- `subscription.label`, `subscription.plural`, `subscription.status.*`
- `customer.label`, `customer.plural`
- `invoice.label`, `invoice.plural`
- `dashboard.title`, `subscriptions.title`, `payment_methods.title`, `invoices.title`
- `intervals.daily`, `intervals.weekly`, `intervals.monthly`, `intervals.yearly`, `intervals.every_*`

### 7.6 Unified Cashier Compatibility

When the `filament-cashier` plugin is detected on the same panel (`$panel->hasPlugin('filament-cashier')`):
- `SubscriptionResource` and `InvoiceResource` are NOT registered (avoids duplicate resources)
- `BillingDashboard` page is NOT registered
- Billing portal pages are NOT registered on the admin panel
- `CustomerResource` IS still registered (no overlap with unified cashier)

This allows `filament-cashier-chip` and `filament-cashier` to coexist on the same panel, with the chip-specific UI deferring to the unified UI where there is overlap.

### 7.7 Polling & Real-Time Updates

All resource tables and the `RevenueChartWidget`/`SubscriptionDistributionWidget` use polling:
- Default interval: `45s` (configurable via `tables.polling_interval`)
- Chart widgets: `120s` fixed interval
- Polling is always active — there is no conditional disable

### 7.8 Database Driver Awareness

The `InvoiceTable` "High Value" filter uses `ConnectionDriver::name()` to construct driver-specific JSON column queries:
- **pgsql**: `(purchase->>'total')::int`
- **mysql/mariadb**: `JSON_UNQUOTE(JSON_EXTRACT(purchase, "$.total"))`
- **sqlite/default**: `json_extract(purchase, '$.total')`

This is the only location where database driver awareness is required. All other queries use Eloquent models with standard accessors.
