# Filament Cashier Lifecycle

## 1. Registration & Installation

**Package identity**: `aiarmada/filament-cashier`, namespace `AIArmada\FilamentCashier`.

**Service provider**: `FilamentCashierServiceProvider` extends `Spatie\LaravelPackageTools\PackageServiceProvider`.

**Registration sequence** (`FilamentCashierServiceProvider`):

1. `configurePackage()` declares the package name `filament-cashier` and enables auto-discovery of:
   - Config file (`config/filament-cashier.php`)
   - Views (`resources/views/`)
   - Translations (`lang/`)

2. `packageRegistered()` binds `FilamentCashierPlugin` as a singleton in the container.

3. `packageBooted()` is a no-op; all panel wiring happens through `FilamentCashierPlugin::register()` when the Filament panel boots.

**Framework integration**: The package declares itself to Filament via `FilamentCashierPlugin` implementing `Filament\Contracts\Plugin`. The host application references it in its PanelProvider:

```php
$panel->plugin(FilamentCashierPlugin::make());
```

**Customer portal**: `BillingPanelProvider` is a standalone `Filament\PanelProvider` that creates a separate Filament panel for end-user billing self-service. This provider is registered in the host app's panel provider list.

---

## 2. Configuration

The config file `filament-cashier.php` has the following top-level keys, each with a distinct lifecycle role:

| Key | Role in lifecycle | Default |
|---|---|---|
| `navigation.group` | Sets the admin sidebar group label for all resources/pages | `Billing` |
| `navigation.sort` | Default sort order for the group | `50` |
| `tables.polling_interval` | AJAX polling interval string for all tables | `45s` |
| `tables.date_format` | PHP date format for table columns | `M d, Y` |
| `gateways.stripe` / `gateways.chip` | Gateway metadata (label, icon, color, dashboard_url) used by `GatewayDetector` | Pre-configured |
| `features.dashboard` | Master toggle for BillingDashboard page + widgets | `true` |
| `features.subscriptions` | Master toggle for UnifiedSubscriptionResource | `true` |
| `features.invoices` | Master toggle for UnifiedInvoiceResource | `true` |
| `features.gateway_management` | Master toggle for GatewayManagement page | `false` |
| `features.customer_portal` | Acts as a secondary customer-portal-mode trigger (OR'd with plugin method) | `false` |
| `resources.navigation_sort.subscriptions` | Sort order override for subscription resource | `10` |
| `resources.navigation_sort.invoices` | Sort order override for invoice resource | `20` |
| `currency.base` | Base currency for MRR display | `MYR` |
| `currency.display_converted` | Whether to sum converted non-base currency MRR | `false` |
| `currency.conversion_rates` | Hardcoded exchange rates for MRR conversion | `MYR => 4.70, USD => 1.00` |
| `billing_portal.*` | Full configuration for the standalone BillingPanelProvider | Various |

**Config fallback chain**: Features are enabled/disabled via a dual-gate: the plugin method flag (e.g., `->dashboard(false)`) AND the config boolean (`features.dashboard`). Both must be truthy for the feature to register. The plugin methods win on navigation overrides (`getNavigationGroup()`, `getNavigationSort()`) but fall back to config values.

---

## 3. Boot & Initialization

`FilamentCashierPlugin::register(Panel $panel)` is the central wiring point. It runs once per panel that registers the plugin.

### Step 1: Gateway detection

```php
$gateways = app(GatewayDetector::class)->availableGateways();
```

`GatewayDetector` (from `commerce-support`) discovers which payment gateways are installed by checking for the existence of their Cashier classes (`Laravel\Cashier\Cashier` for Stripe, `AIArmada\CashierChip\Billing\Cashier` for CHIP). It also provides label/icon/color metadata from `config('filament-cashier.gateways')`.

### Step 2: No-gateway escape hatch

If `$gateways->isEmpty()`, the plugin registers only `GatewaySetup::class` as a page. This renders the setup wizard view (`gateway-setup.blade.php`) showing installation instructions for each gateway. The `GatewaySetup` page checks `class_exists()` to mark each gateway as available/unavailable.

### Step 3: Feature resolution

```php
$customerPortalMode = $this->customerPortalMode || config('filament-cashier.features.customer_portal');
$enableDashboard = $this->enableDashboard && config('filament-cashier.features.dashboard');
$enableSubscriptions = $this->enableSubscriptions && config('filament-cashier.features.subscriptions');
$enableInvoices = $this->enableInvoices && config('filament-cashier.features.invoices');
$enableGatewayManagement = $this->enableGatewayManagement && config('filament-cashier.features.gateway_management');
```

### Step 4: Resource/Page/Widget registration

Based on resolved feature flags:

| Condition | Registered |
|---|---|
| `$enableSubscriptions` | `UnifiedSubscriptionResource` |
| `$enableInvoices` | `UnifiedInvoiceResource` |
| `$enableDashboard && !$customerPortalMode` | `BillingDashboard` page + all 5 dashboard widgets |
| `$enableGatewayManagement && !$customerPortalMode` | `GatewayManagement` page |

The customer portal mode suppresses admin-only pages (dashboard, gateway management) but preserves resources (subscriptions, invoices).

**Widgets registered for dashboard** (in order):

1. `TotalMrrWidget` (sort=1)
2. `TotalSubscribersWidget` (sort=2)
3. `GatewayBreakdownWidget` (sort=3, columnSpan=1)
4. `GatewayComparisonWidget` (sort=4, columnSpan=2)
5. `UnifiedChurnWidget` (sort=5)

### Step 5: BillingPanelProvider initialization

When the host app registers `BillingPanelProvider`, it creates a separate Filament panel with:
- Panel ID and path from `billing_portal.*` config
- Brand name and primary color from config (or env vars)
- Auth guard from config (default: `web`)
- Four pages: `BillingOverview`, `ManageSubscriptions`, `ManagePaymentMethods`, `ViewInvoices`
- Standard Filament middleware stack including `commerce_csrf_middleware()`
- Optional login page (`$config['login_enabled'] ?? true`)

**Customer portal feature toggles** (from `billing_portal.features`):
- `subscriptions` controls whether "New Subscription" action appears in ManageSubscriptions
- `payment_methods` and `gateway_switching` control visibility in ManagePaymentMethods
- `invoices` controls ViewInvoices availability

---

## 4. Request Lifecycle

### 4.1 Admin Panel Request

When a user navigates to any filament-cashier page in the admin panel:

**Route resolution**:
- `BillingDashboard` uses `routePath: 'billing-dashboard'` and `slug: 'billing-dashboard'`
- `UnifiedSubscriptionResource` maps `/` to `ListSubscriptions`, `/{record}` to `ViewSubscription`, `/create` to `CreateSubscription`
- `UnifiedInvoiceResource` maps `/` to `ListInvoices` only (no view/create pages)
- `GatewayManagement` and `GatewaySetup` use standard Filament page routing
- `GatewaySetup` has a fixed `navigationSort: 100` and `GatewayManagement` has `navigationSort: 50`

**Resource model resolution**: Both `UnifiedSubscriptionResource::getModel()` and `UnifiedInvoiceResource::getModel()` resolve to gateway-specific Eloquent models through a priority chain:
1. If Stripe Cashier is installed: `Laravel\Cashier\Subscription` / `AIArmada\Chip\Models\Purchase`
2. Otherwise: the app's User model
3. The return type is always an Eloquent model FQCN

**Route-model binding disabled**: Both resources override `resolveRecordRouteBinding()` to return `null`, disabling Eloquent route-model binding. Instead, composite keys (`gateway-id`) are parsed manually in `ViewSubscription::mount()`.

### 4.2 Table Record Lifecycle

Tables do **not** use Eloquent query builders. Instead:

1. `ListSubscriptions::getTableRecords()` calls `getFilteredSubscriptions()` which returns a `Collection` of `UnifiedSubscriptionRecord` instances
2. `ListInvoices::getTableRecords()` calls `getFilteredInvoices()` which returns a `Collection` of `UnifiedInvoiceRecord` instances

**Data aggregation flow**:

```
getAllSubscriptions() / getAllInvoices()
  |-- Stripe: OwnerScopedQuery::apply(Subscription::query())
  |     ->with('items')->where('user_id', $userId)->get()
  |     ->map(fn => UnifiedSubscription::fromStripe($sub))
  |
  |-- CHIP: OwnerScopedQuery::apply($subscriptionModel::query())
  |     ->with(['billable', 'items'])
  |     ->where('billable_type', ...)->where('billable_id', ...)->get()
  |     ->map(fn => UnifiedSubscription::fromChip($sub))
  |
  |-- Unified DTO becomes UnifiedSubscriptionRecord::forceFill([...])
```

**Compositing**: For invoices, Stripe data comes from `$user->invoices()` (API call), CHIP data from `Purchase::query()` (DB).

**Tab filtering**: Both list pages construct tabs dynamically:
- `all` tab: full collection
- Per-gateway tabs (e.g., `stripe`, `chip`): `where('gateway', $gateway)`
- Subscriptions also has `active` (status filter) and `issues` (PastDue/Incomplete) tabs

**Filter queries are no-ops**: The table `SelectFilter` query callbacks return the builder unchanged because filtering happens at the collection level in `getFilteredSubscriptions()` / `getFilteredInvoices()`.

### 4.3 Widget Data Flow

Each widget aggregates data across all available gateways:

**TotalMrrWidget** (`pollingInterval: 60s`):
1. Calls `getActiveSubscriptionsSummary()` (cached via `once()`)
2. Iterates Stripe subscriptions via `Subscription::query()` chunked at 200
3. Iterates CHIP subscriptions via `CashierChip::$subscriptionModel` chunked at 200
4. Each active subscription's `amount` is added to `$mrrByCurrency[$currency]`
5. If `currency.display_converted`, non-base currencies are converted using `conversion_rates`
6. Formats via `CurrencyFormatter::format($amountInCents, $currency)`

**TotalSubscribersWidget** (`pollingInterval: 60s`):
1. Counts active subscriptions from Stripe and CHIP
2. Builds a breakdown description string: `Stripe: N | CHIP: N`

**GatewayBreakdownWidget** (`pollingInterval: 120s`):
1. Computes revenue-by-gateway via the same chunk iteration pattern
2. Renders as a doughnut chart with gateway-specific color mapping

**GatewayComparisonWidget** (`pollingInterval: 120s`):
1. Generates 6-month monthly data for each gateway
2. Revenue per month = count * hardcoded average (2900 cents Stripe, 9900 cents CHIP)
3. Renders as a line chart with currency symbol from `MoneyFormatter::symbol()`

**UnifiedChurnWidget** (`pollingInterval: 120s`):
1. Counts cancellations (`ends_at` not null) for current month and last month
2. Computes percentage trend

### 4.4 Gateway Health Checks

`GatewayManagement::getGatewayHealth()` is called on page render:

**Stripe health**:
1. Resolves secret from `services.stripe.secret` -> fallback `cashier.gateways.stripe.secret`
2. Detects placeholder secrets (containing `xxx` or `placeholder`)
3. Tests connection via `Stripe::setApiKey()` -> `Account::retrieve()` with previous-key restoration in `finally`
4. Returns `healthy` / `not_configured` / `error` / `unknown`

**CHIP health**:
1. Reads `chip.brand_id` -> fallback `cashier.gateways.chip.brand_id`
2. Reads `chip.api_key` -> fallback `chip.collect.api_key`
3. Tests connection via `Chip::brands()->first()`
4. Same status taxonomy

**Default gateway caching**: The "Set Default" action writes to `OwnerCache` keyed by `filament-cashier.default_gateway.{panelId}`, scoped to the current owner context. Read path falls back to `config('cashier.default')`.

---

## 5. Domain Operations

### 5.1 Subscription Creation

`CreateSubscription::handleRecordCreation()` orchestrates a multi-step creation:

1. **Customer validation**: Loads the billable model from config, applies `OwnerScopedQuery` to ensure the selected `billable_id` belongs to the current owner scope
2. **Contract check**: Verifies the billable model implements `BillableContract`
3. **Gateway availability**: Confirms the selected gateway exists via `GatewayDetector::isAvailable()`
4. **Subscription builder**: `Cashier::gateway($gateway)->newSubscription($billable, $type, $planId)`
5. **Optional quantity**: Applied if `> 1`
6. **Optional trial**: `->trialDays()` if `has_trial` toggle is on
7. **Payment method validation** (if selected): `billableOwnsPaymentMethod()` verifies the payment method actually belongs to the billable by fetching `$billable->paymentMethods()` and matching IDs
8. **Creation**: `$builder->create($paymentMethodId)` or `$builder->create()`

### 5.2 Subscription Cancellation

Available in three contexts:
- **Admin table**: `SubscriptionsTable` cancel action + bulk cancel
- **Admin view page**: `ViewSubscription` cancel header action
- **Customer portal**: `ManageSubscriptions::cancelSubscription()`

All cancellation flows follow the same pattern:
1. Check `$subscription->status->isCancelable()` (visibility gate)
2. Apply `SubscriptionPolicy::cancel()` authorization
3. Call `$subscription->original->cancel()` on the underlying gateway model

### 5.3 Subscription Resumption

Same pattern as cancellation but uses `->isResumable()` gate and `->resume()` method.

### 5.4 Invoice Export

`InvoicesTable` provides a bulk CSV export action that streams a CSV response:
- Headers: Invoice #, Gateway, Amount, Status, Date, Paid At
- Rows from `$invoice->getAttribute(...)`
- Filename: `invoices-YYYY-MM-DD.csv`

### 5.5 Payment Method Management (Customer Portal)

`ManagePaymentMethods` provides:
- **List**: Aggregates Stripe `$user->paymentMethods()` and CHIP `$user->chipPaymentMethods()`
- **Set default**: Calls `$user->updateDefaultPaymentMethod($methodId)` (Stripe) or `$user->updateDefaultChipPaymentMethod($methodId)` (CHIP)
- **Delete**: Calls `$user->findPaymentMethod($methodId)->delete()` (Stripe) or `$user->deleteChipPaymentMethod($methodId)` (CHIP)

All operations validate gateway availability and user ownership before execution.

### 5.6 CustomerSubscriptionsQuery (Shared Query Logic)

`CustomerSubscriptionsQuery` is a reusable support class for aggregating user subscriptions:
- **Stripe path**: `Subscription::query()` scoped via `OwnerScopedQuery`, filtered by `user_id` (not billable morph), with `fetchExtra` pattern for "has more" detection
- **CHIP path**: `CashierChip::$subscriptionModel` scoped via `OwnerScopedQuery`, filtered by `billable_type`/`billable_id` morph columns
- **Table existence check**: Stripe path checks `Schema::hasTable()` before querying (defensive for environments where Stripe migrations haven't run)
- **Identifier resolution**: Uses `getAttributes()`, `getKey()`, `getRawOriginal()` in priority order

### 5.7 Plan Discovery

`SubscriptionForm::getPlansForGateway()` resolves plans in priority order:
1. Config key `cashier.gateways.{gateway}.plans` if non-empty
2. Hardcoded fallback plans per gateway with pre-formatted price strings via `MoneyFormatter::formatMajor()`

### 5.8 CHIP Payment Method Resolution

The `SubscriptionForm` provides static helpers `getChipPaymentMethodId()` and `getChipPaymentMethodLabel()` that handle CHIP's polymorphic payment method objects. The ID resolver checks `id()`, then `data_get('id')`, then `data_get('recurring_token')`. The label resolver checks `brand()`, `type()`, `card_brand`, `last_four`, `last_4`, `card_last_4`, `card_last4`, `last4` in priority order, across both object methods and `data_get()` access.

---

## 6. Customer Portal Lifecycle

### 6.1 Panel Initialization

`BillingPanelProvider::panel()` creates a fully independent Filament panel:

```
Panel ID:     config('filament-cashier.billing_portal.panel_id', 'billing')
Path:         config('filament-cashier.billing_portal.path', 'billing')
Brand:        env('FILAMENT_CASHIER_BRAND_NAME', 'Billing Portal')
Primary:      env('FILAMENT_CASHIER_PRIMARY_COLOR', '#6366f1') mapped to Color preset
Auth guard:   config('filament-cashier.billing_portal.auth_guard', 'web')
```

The primary color hex is matched to a Filament `Color::*` preset (Indigo, Blue, Emerald, Amber, Red, Violet) via `parsePrimaryColor()`.

### 6.2 Portal Pages

| Page | Sort | View file | Key methods |
|---|---|---|---|
| `BillingOverview` | 0 | `customer-portal.billing-overview` | `getHeaderWidgets()` |
| `ManageSubscriptions` | 1 | `customer-portal.manage-subscriptions` | `getSubscriptions()`, `cancelSubscription()`, `resumeSubscription()`, `loadMoreSubscriptions()` |
| `ManagePaymentMethods` | 2 | `customer-portal.manage-payment-methods` | `getPaymentMethods()`, `setDefaultPaymentMethod()`, `deletePaymentMethod()` |
| `ViewInvoices` | 3 | `customer-portal.view-invoices` | `getInvoices()` |

### 6.3 BillingOverview Widgets

Three header widgets rendered in a responsive grid (1 column on sm, 2 on md, 3 on lg):

1. **ActiveSubscriptionsWidget** (sort=1, columnSpan=full): Fetches active subscriptions from both gateways (limit 5 per gateway), owned by authenticated user, filtered to `status->isActive()`
2. **PaymentMethodsPreviewWidget** (sort=2, columnSpan=1): Shows the default payment method per gateway (`$user->defaultPaymentMethod()` / `$user->defaultChipPaymentMethod()`)
3. **RecentInvoicesWidget** (sort=3, columnSpan=1): Aggregates Stripe invoices (via reflection to detect API style) and CHIP invoices, returns latest 5

### 6.4 ManageSubscriptions Load-More Pattern

`ManageSubscriptions` supports incremental loading:
- `perGatewayLimit` starts at 50
- `loadMoreSubscriptions($increment)` increases limit by `DEFAULT_LOAD_MORE_INCREMENT` (50)
- `getForUser()` uses `fetchExtra: true` to get limit+1 records per gateway, setting `hasMoreSubscriptions` if any gateway returned a full batch

### 6.5 Invoice Resolution (Reflection-Based)

`RecentInvoicesWidget::resolveStripeInvoices()` uses `ReflectionMethod` to inspect the `invoices()` signature, handling two Laravel Cashier API conventions:
- **Array-style**: `invoices(['limit' => 3])` when first parameter is named `options` or typed `array`
- **Legacy-style**: `invoices(false, 'stripe')` when first parameter is a boolean

### 6.6 Authorization

Two policies govern all customer portal operations. Both handle the dual ownership column pattern (Stripe uses `user_id`, CHIP uses `billable_type`/`billable_id`).

**SubscriptionPolicy**:
- `viewAny` -> always `true` (users see only their own via query scoping)
- `view`, `cancel`, `resume`, `update`, `swap` -> checks ownership via `billable_type`/`billable_id` then `user_id` column matching
- Identifier resolution uses `getAttributes()`, `getRawOriginal()`, and morph class comparison

**PaymentMethodPolicy**:
- `viewAny`, `create` -> always `true`
- `view`, `update`, `delete`, `setDefault` -> checks ownership via same dual-column strategy as SubscriptionPolicy

---

## 7. Teardown & Cleanup

### 7.1 No Persistent State

The package creates **no database tables, no migrations, no models**. All persistence is delegated to the gateway-specific packages (`laravel/cashier`, `cashier-chip`). The `database/migrations/` directory does not exist.

### 7.2 Cache Considerations

- `GatewayManagement` writes to `OwnerCache` for the default gateway preference. This cache entry is scoped by owner context and panel ID.
- If the package is removed, the cache key `filament-cashier.default_gateway.{panelId}` may persist in the cache store but has no effect without the package running.
- Widgets use `once()` for request-scoped caching only (no persistent cache).

### 7.3 Panel Unregistration

- If `BillingPanelProvider` is removed from the host app's panel providers list, the customer portal panel ceases to exist. No cleanup is needed.
- If `FilamentCashierPlugin` is removed from the admin panel's plugin list, all resources, pages, and widgets are unregistered. Routes disappear. No data cleanup is needed.

### 7.4 Gateway Absence

- When `GatewayDetector::availableGateways()` returns an empty collection, the plugin degrades to showing only the `GatewaySetup` page. All other features are hidden.
- If a gateway becomes unavailable at runtime (e.g., SDK class removed), individual widget methods and table data sources silently return empty collections via `class_exists()` / `isAvailable()` guards.
- Schema checks: The Stripe subscription path includes `Schema::hasTable()` defense for environments where Stripe migrations haven't run yet.

### 7.5 Error Recovery

All gateway API calls are wrapped in `try/catch(Throwable)`:

- `GatewayManagement::checkStripeHealth()` / `checkChipHealth()` return `status: error` / `color: danger` with the exception message
- Customer portal invoice fetching catches exceptions and logs them via `Log::debug()`
- Payment method fetching returns empty arrays on any `Throwable`
- `GatewaySetup::getGateways()` uses `class_exists()` rather than API calls, so it cannot fail
- The `GatewayDetector` (from `commerce-support`) provides the gateway availability contract and is the single source of truth for whether a gateway is operational

### 7.6 Plugin Deprecation Path

The `FilamentCashierPlugin` contains legacy method aliases (`enableDashboard`, `enableSubscriptions`, `enableInvoices`, `enableGatewayManagement`) that delegate to the canonical short-form methods (`dashboard`, `subscriptions`, `invoices`, `gatewayManagement`). These are marked `@deprecated` and exist only for call-site migration compatibility.

### 7.7 Octane Safety

- No process-wide mutable statics
- No request-leaking singletons (the plugin singleton is configuration-only)
- `once()` in widgets is request-scoped and safe under Octane
- `OwnerCache` and `OwnerContext` from `commerce-support` are designed for Octane compatibility
