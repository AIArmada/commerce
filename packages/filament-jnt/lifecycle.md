# Filament JNT Lifecycle

## 1. Package Bootstrap

**Service Provider**: `FilamentJntServiceProvider`

**Registration** (`packageRegistered`):
- Registers `FilamentJntPlugin` as a singleton in the container.

**Boot** (`packageBooted`):
- Binds three Gate policies: `JntOrder` → `JntOrderPolicy`, `JntTrackingEvent` → `JntTrackingEventPolicy`, `JntWebhookLog` → `JntWebhookLogPolicy`.

Models (`JntOrder`, `JntTrackingEvent`, `JntWebhookLog`) live in the `aiarmada/jnt` package. This package owns only the Filament UI layer. It has **no migrations** and **no database layer** of its own.

## 2. Plugin Registration on Panel

**Plugin**: `FilamentJntPlugin`

Consumer panels register via `$panel->plugin(FilamentJntPlugin::make())`. Fluent enable/disable setters:

```php
FilamentJntPlugin::make()
    ->orders(false)
    ->trackingEvents()
    ->webhookLogs()
    ->widgets()
```

Each setter defaults to `true` when called without arguments.

**`register(Panel)`** resolves resources/widgets by checking fluent overrides first, falling back to config defaults:

| Feature | Config Key | Default |
|---|---|---|
| Orders resource | `features.orders` | `true` |
| Tracking Events | `features.tracking_events` | `true` |
| Webhook Logs | `features.webhook_logs` | `true` |
| Dashboard widgets | `features.widgets` | `true` |

**`boot(Panel)`**: Currently a no-op placeholder.

## 3. Resource Instantiation & Query Scoping

**Base Resource**: `BaseJntResource`

Every JNT resource extends `BaseJntResource`.

### Navigation Configuration

- **Group**: From `config('filament-jnt.navigation_group')` (default `'Shipping'`).
- **Sort order**: From `config('filament-jnt.resources.navigation_sort.{key}')` where `{key}` is `orders`, `tracking_events`, or `webhook_logs`.
- **Badge**: Computed via `NavigationBadgeHelper::getNavigationBadge()`, cached per-owner for 30 seconds. Returns `null` when unauthenticated or count is zero.
- **Badge color**: From `config('filament-jnt.navigation_badge_color')` (default `'primary'`).

### Owner-Scoped Eloquent Query

`getEloquentQuery()` calls `OwnerUiScope::apply()` on the parent query, passing `includeGlobal` from `config('jnt.owner.include_global')`.

`$tenantOwnershipRelationshipName` is set to `'owner'` for Filament tenancy integration.

### Polling Interval

Tables poll at `config('filament-jnt.polling_interval')` (default `'30s'`).

## 4. Page Lifecycle — List Records

**Base page class**: `ReadOnlyListRecords` (from `commerce-support`).

| Resource | Page Class | Title |
|---|---|---|
| `JntOrderResource` | `ListJntOrders` | "J&T Express Orders" |
| `JntTrackingEventResource` | `ListJntTrackingEvents` | "J&T Tracking Events" |
| `JntWebhookLogResource` | `ListJntWebhookLogs` | "J&T Webhook Logs" |

Each list page overrides `getTitle()` and `getSubheading()` only. The table is configured by a dedicated static `*Table::configure(Table)` method.

### JNT Orders Table (`JntOrderTable`)

Columns: `order_id`, `tracking_number`, `customer_code`, `express_type`, `service_type`, `last_status_code` (normalized via `JntStatusMapper`), `has_problem` (boolean icon), `chargeable_weight`, `package_value` (MYR formatted), `cod_value` (MYR formatted), `delivered_at`, `created_at`.

Filters: Normalized status (`SelectFilter` with `TrackingStatus` options using `applyNormalizedStatusFilter`), express type, service type, has problem (toggle), delivered (toggle), pending delivery (toggle).

Actions: `ViewAction` (icon only), `PrintAwbTableAction`.

Default sort: `created_at` desc. Pagination: 25/50/100. Polling: per config.

### JNT Tracking Events Table (`JntTrackingEventTable`)

Columns: `tracking_number`, `order_reference`, `scan_type_code` (normalized status badge), `scan_time`, `description` (truncated), `scan_network_name`, `scan_network_city`, `problem_type` (icon), `staff_name`, `created_at`.

Filters: Normalized status, has problem (toggle), delivered (toggle using `applyDeliveredStatusFilter`).

Actions: `ViewAction` only.

### JNT Webhook Logs Table (`JntWebhookLogTable`)

Columns: `tracking_number`, `order_reference`, `processing_status` (badge colored by `processed`/`pending`/`failed`), `processing_error` (truncated), `processed_at`, `created_at`.

Filters: Processing status select filter.

Actions: `ViewAction` only.

## 5. Page Lifecycle — View Record

**Base page class**: `ReadOnlyViewRecord` (from `commerce-support`).

| Resource | Page Class | Title Pattern |
|---|---|---|
| `JntOrderResource` | `ViewJntOrder` | `"Order {order_id or key}"` |
| `JntTrackingEventResource` | `ViewJntTrackingEvent` | `"Tracking {tracking_number}"` |
| `JntWebhookLogResource` | `ViewJntWebhookLog` | `"Webhook {tracking_number}"` |

### View JNT Order — Header Actions

1. **Print AWB** (`PrintAwbTableAction`)
2. **Sync Tracking** (`SyncTrackingAction`)
3. **Cancel Order** (`CancelOrderAction`)

### View Record Infolists

Each resource has a dedicated static `*Infolist::configure(Schema)` method.

**`JntOrderInfolist`**: Order Summary (tracking, status badge, express/service/payment types, problem flag), Sender/Receiver (name, phone, address, area, city, state, post code), Package Details (quantity, weight, chargeable weight, goods type, dimensions), Financials (declared value, insurance, COD — MYR formatted), Timeline (ordered, pickup, delivered, last synced, last tracked), Tracking Events (repeatable from `trackingEvents` relation), Items (repeatable from `items` relation), Notes (remark — visible when filled), Raw Data (payloads, metadata — gated behind `features.show_raw_payloads`).

**`JntTrackingEventInfolist`**: Event Summary, Location (network name/type, area, city, province, post code, contact, lat/lng), Staff & Delivery (staff name/contact, OTP, actual weight), Signature (picture/sign URL), Problem Details (problem type badge, remark — visible when filled), Raw Payload (gated).

**`JntWebhookLogInfolist`**: Webhook Summary (tracking number, order reference, processing status badge, timestamps), Error Details (visible when filled), Request Details (digest, headers — gated), Payload (JSON — gated), Related Order (link to `JntOrderResource` view page).

## 6. Action Execution

### Print AWB (`PrintAwbTableAction`)

Visible when `order_id` is non-empty.

**Flow**:
1. Confirm authentication.
2. `recordIsAccessible()`: validates via `OwnerWriteGuard::findOrFailForOwner()` if `jnt.owner.enabled`.
3. Resolves `JntExpressService`, calls `$jntService->printOrder()`.
4. URL content: opens in new tab via Livewire JS, success notification.
5. base64 content: generates signed download URL via `OwnerSignedDownload::issueUrl()` (30min TTL, scoped to owner + user), opens in new tab.
6. On `Throwable`: reports exception, danger notification.

### Sync Tracking (`SyncTrackingAction`)

Visible when `tracking_number` is non-null.

**Flow**:
1. Authentication and owner-accessibility checks.
2. Resolves `JntTrackingService`, calls `$trackingService->syncOrderTracking($record)`.
3. Refreshes the model.
4. Success or failure notification.

### Cancel Order (`CancelOrderAction`)

Visible when `$record->status` is not in `['delivered', 'cancelled', 'returned']`.

**Form fields**:
- `Select` for `reason` (options grouped by: Customer-Initiated, Merchant-Initiated, Delivery Issues, Payment Issues, Other).
- `Textarea` for `custom_reason` (max 255 chars, visible only when reason is `OTHER`).

**Flow**:
1. Authentication and owner-accessibility checks.
2. `validateCancellationRequest()`: validates reason is valid `CancellationReason`, requires custom_reason when reason is `OTHER`.
3. Resolves `JntExpressService`, calls `$jntService->cancelOrder()`.
4. Updates local record: `status = 'cancelled'`, `cancelled_at = now()`, `cancellation_reason = reason_string`.
5. Success or failure notification.

## 7. Dashboard Widget

**`JntStatsWidget`** extends `StatsOverviewWidget`. Registered when `features.widgets` is enabled.

### Stats Computation

**Cache key**: `filament-jnt:widget:stats:{ownerMorphClass}:{ownerKey}:{includeGlobal}` — scoped per-owner. TTL: 30 seconds.

`JntStatsAggregator::calculateOrderStats()` runs a single aggregate `SELECT` on `JntOrder`:
- `total`: `COUNT(*)`
- `delivered`: `SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END)`
- `in_transit`: `SUM(CASE WHEN delivered_at IS NULL AND tracking_number IS NOT NULL AND has_problem = 0 THEN 1 ELSE 0 END)`
- `problems`: `SUM(CASE WHEN has_problem = 1 THEN 1 ELSE 0 END)`
- `pending`: `SUM(CASE WHEN tracking_number IS NULL THEN 1 ELSE 0 END)`
- `returns`: `SUM(CASE WHEN last_status_code IN ('172','173') THEN 1 ELSE 0 END)`

When `jnt.owner.enabled` is true, scoped via `OwnerUiScope::apply()` with `includeGlobal` from config.

### Displayed Stats (6-column layout)

| Stat | Color | Description |
|---|---|---|
| Total Orders | primary | Count |
| Delivered | success | Count + delivery rate percentage |
| In Transit | info | Count |
| Pending | warning | Count |
| Returns | purple (if >0) / gray | Count |
| Problems | danger (if >0) / success | Count |

### Authorization

All policies (`JntOrderPolicy`, `JntTrackingEventPolicy`, `JntWebhookLogPolicy`) delegate to `OwnerUiScope`:
- `viewAny` / `create` / `deleteAny`: `OwnerUiScope::canCreate(Model::class)`.
- `view`: `OwnerUiScope::canAccessRecord($model)`.
- `update` / `delete` / `restore` / `forceDelete`: `OwnerUiScope::canMutateRecord($model)`.
