# Filament Shipping Lifecycle

## 1. Package Overview

**Composer**: `aiarmada/filament-shipping`
**Namespace**: `AIArmada\FilamentShipping`
**Role**: Filament admin panel integration for `aiarmada/shipping`. Provides Filament resources, pages, widgets, and actions for managing shipments, shipping zones, shipping rates, and return authorizations.

This package does **not** own domain models, state machines, or persistence. Those live in `aiarmada/shipping`. It has **no database migrations** — all tables are defined by the `shipping` package.

## 2. Installation & Registration

### Service Provider
`FilamentShippingServiceProvider` (extends `PackageServiceProvider`) registers the config file `filament-shipping.php`, Blade views, and a singleton binding for `CartBridge`.

### Panel Plugin
`FilamentShippingPlugin` implements `Filament\Contracts\Plugin`. Plugin ID: `filament-shipping`.

The plugin registers four resources, three pages, and four widgets on the panel. Each can be individually toggled via fluent setters:

| Toggle Method | Default | Controls |
|---|---|---|
| `shipmentResource()` | `true` | `ShipmentResource` |
| `shippingZoneResource()` | `true` | `ShippingZoneResource` |
| `shippingRateResource()` | `true` | `ShippingRateResource` |
| `returnAuthorizationResource()` | `true` | `ReturnAuthorizationResource` |
| `dashboardWidgets()` | `true` | All 4 widgets |
| `shippingDashboard()` | `true` | `ShippingDashboard` page |
| `manifestPage()` | `true` | `ManifestPage` page |
| `fulfillmentQueue()` | `true` | `FulfillmentQueue` page (gated by `features.enable_fulfillment_queue` config) |

## 3. Configuration

**File**: `config/filament-shipping.php`

| Key | Type | Default | Purpose |
|---|---|---|---|
| `shipping_methods` | `array<string,string>` | `['standard' => 'Standard', ...]` | Available shipping method labels for FulfillmentQueue filter |
| `carriers` | `array` | `[]` | Carrier list; falls back to `shipping.drivers` |
| `features.enable_fulfillment_queue` | `bool` | `true` | Whether the Fulfillment Queue page is rendered |
| `fulfillment.urgent_threshold_hours` | `int` | `48` | Orders older than this are marked "urgent" |
| `fulfillment.old_threshold_hours` | `int` | `24` | "Older than 24h" filter default |

## 4. Owner Scoping

Every resource's `getEloquentQuery()` enforces owner scoping when `shipping.features.owner.enabled` is `true`:

1. Remove the global `OwnerScope`.
2. If owner mode is **off**, return the query as-is.
3. Resolve the current owner via `OwnerContext::resolve()`.
4. If no owner is resolved, return `whereRaw('0 = 1')` (empty result).
5. Otherwise, apply `->forOwner($owner, includeGlobal: ...)`.

`ShippingRateResource` scopes indirectly through the zone relationship (`whereHas('zone', ...)`). `ManifestPage` and `FulfillmentQueue` also apply owner scoping explicitly on their table queries.

## 5. Filament Resources & Pages

### Navigation Hierarchy
Under the "Shipping" navigation group, in sort order:

| Sort | Entity | Route | Icon |
|---|---|---|---|
| 0 | **ShippingDashboard** (Page) | `/shipping-dashboard` | Chart bar |
| 1 | **FulfillmentQueue** (Page) | (auto) | Cube |
| 1 | **ShipmentResource** | `/shipments` | Truck |
| 2 | **ShippingZoneResource** | `/shipping-zones` | Map |
| 3 | **ReturnAuthorizationResource** | `/returns` | Arrow uturn left |
| 3 | **ShippingRateResource** | `/shipping-rates` | Currency dollar |
| 5 | **ManifestPage** (Page) | `/shipping-manifests` | Document text |

**Navigation consistency gap**: Sort order 3 is shared by ReturnAuthorizationResource and ShippingRateResource, so their relative order in the sidebar depends on registration order rather than an explicit sort.

### ShipmentResource
- **Pages**: List, Create, View, Edit
- **Relations**: `ItemsRelationManager`, `EventsRelationManager`
- **Table** (`ShipmentsTable`): Columns for reference, carrier, tracking, status (badge with icon/color), weight (g/kg adaptive), cost (money), shipped_at, created_at (togglable). Filters by status and carrier. Actions: View, Edit, Ship, Print Label, Cancel, Sync Tracking. Bulk actions for all of the above plus Delete.
- **Form** (`ShipmentForm`): Shipment Details (reference, carrier, service_code, status, tracking_number), Origin/Destination Address (KeyValue), Package Info (weight with g/kg conversion, declared_value with currency prefix, shipping_cost with currency prefix). Monetary fields hydrate/dehydrate between minor units (int) and display units (float).

### ShippingZoneResource
- **Pages**: List, Create, Edit
- **Relations**: `RatesRelationManager`
- **Table** (`ShippingZonesTable`): Columns for name, code (badge), type (badge color-coded), priority, is_default (icon boolean), active (icon boolean), rates_count. Filters by type and active. Actions: Edit. Bulk: Delete.
- **Form** (`ShippingZoneForm`): Zone Details (name, code with owner-scoped uniqueness, type, priority, is_default, active). Geographic Conditions: `countries` (TagsInput, type=country/state), `states` (TagsInput, type=state), `postcode_ranges` (Repeater, type=postcode), `center_lat`/`center_lng`/`radius_km` (Grid, type=radius).

### ShippingRateResource
- **Pages**: List, Create, Edit
- **Table** (`ShippingRatesTable`): Columns for zone.name, name, method_code (badge), carrier_code (badge), calculation_type (color-coded badge), base_rate (formatted), delivery_estimate, active (icon boolean). Filters by zone (with OwnerQuery scoping), calculation_type, carrier_code, active (ternary). Actions: Edit. Bulk: Delete.
- **Form** (`ShippingRateForm`): Rate Details (zone_id with owner-scoped options, name, method_code, carrier_code, calculation_type with live reactive toggle, active). Pricing (base_rate with currency prefix/minor unit conversion, per_unit_rate conditionally visible, min/max_charge, free_shipping_threshold, rate_table for "table" type). Delivery Estimate (estimated_days_min/max, description). Conditions (Repeater, collapsible). Create/Edit pages validate zone ownership server-side.
- **RatesRelationManager** (on Zone): Mirrors the form schema for inline CRUD.

### ReturnAuthorizationResource
- **Pages**: List, Create, View, Edit
- **Relations**: `ItemsRelationManager`
- **Table** (`ReturnAuthorizationsTable`): Columns for rma_number, order_reference, status (badge color-coded), type (badge), reason, items_count, created_at. Filters by status and type. Actions: View, Edit, Approve, Reject. Bulk: Delete.
- **Form** (`ReturnAuthorizationForm`): Return Details (rma_number disabled/dehydrated false, order_reference, status, type, reason, reason_details). Timeline (approved_at disabled, received_at disabled, completed_at disabled, expires_at).
- **ItemsRelationManager**: Form with name, sku, quantity, quantity_received, condition (select), notes. Table with identical columns plus condition filter.

### Custom Pages

**FulfillmentQueue**: Displays orders in "Processing" state from the `orders` package. Navigation badge shows processing order count (cached 15s, owner-scoped, color-coded by urgent threshold). Table with order_number, created_at, customer info, items_count badge, grand_total money, status badge, shipping_method, priority badge. Filters for older/urgent/shipping method. Row actions: Ship (modal with carrier + tracking number), View. Polls every 30s.

**ManifestPage**: Displays shipped shipments, filterable by carrier and date. Table with reference, tracking_number (copyable), carrier_code badge, destination, weight, picked_up (icon), shipped_at. Row/bulk actions: Mark Picked Up. Header actions: Generate Manifest PDF, Mark All Picked Up.

**ShippingDashboard**: Header widgets (5-column): `ShippingDashboardWidget` (Pending, In Transit, Delivered Today, Exceptions, Pending Returns). Footer: `CarrierPerformanceWidget` (stacked bar chart, 30-day window), `PendingShipmentsWidget` (10 most recent pending), `PendingActionsWidget` (4 clickable stats).

## 6. Actions & Widgets

### Actions

| Action | Visibility | Behavior |
|---|---|---|
| `ShipAction` | `$record->isPending()` | Delegates to `ShipmentService::ship()`. Bulk groups by carrier with `BatchRateLimiter`. |
| `CancelShipmentAction` | `$record->isCancellable()` | Delegates to `ShipmentService::cancel()`. Requires confirmation. Bulk groups by carrier. |
| `SyncTrackingAction` | `tracking_number !== null` | Delegates to `TrackingAggregator::syncTracking()`. Bulk groups by carrier. |
| `PrintLabelAction` | `tracking_number !== null` | URL labels open in new tab; content labels cached (30min TTL) with signed URL. |
| `ApproveReturnAction` | `$record->isPending()` | Delegates to `ApproveReturnAuthorization::run()`. |
| `RejectReturnAction` | `$record->isPending()` | Modal with required rejection reason. Delegates to `RejectReturnAuthorization::run()`. |

### Widgets

| Widget | Type | Polling | Content |
|---|---|---|---|
| `ShippingDashboardWidget` | `StatsOverviewWidget` | 30s | 5 stats via `ShippingStatsAggregator` |
| `CarrierPerformanceWidget` | `ChartWidget` (bar) | 60s | Per-carrier Delivered/InTransit/Exceptions over 30 days |
| `PendingShipmentsWidget` | `TableWidget` | none | 10 latest pending shipments |
| `PendingActionsWidget` | `StatsOverviewWidget` | 30s | 4 clickable stats with filtered URLs |

### Support Classes

`ShippingStatsAggregator`: Centralized query object for shipment statistics. All methods apply owner scoping (same pattern: remove OwnerScope, check config, resolve owner, apply forOwner or return empty).

## 7. Owner-Scoped Write Validation

ShippingRate Create/Edit pages validate zone ownership server-side in `mutateFormDataBeforeCreate`/`mutateFormDataBeforeSave`:

1. If `shipping.features.owner.enabled`: resolve owner via `OwnerContext`.
2. If no owner → throw `ValidationException`.
3. Query `ShippingZone` without `OwnerScope`, match by id and owner tuple.
4. If zone does not exist under owner → throw `ValidationException`.

All stats queries (widgets, badges) apply the same owner-scoping pattern as resource queries.
