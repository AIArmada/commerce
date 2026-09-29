---
title: Usage
---

# Usage

Guide to using the Filament JNT resources, actions, and widgets.

---

## Orders Resource

The JntOrderResource provides a full-featured interface for managing shipping orders.

### List View

The orders table includes:

| Column | Description |
|--------|-------------|
| Order ID | Your order reference (copyable) |
| Tracking # | J&T tracking number (copyable) |
| Customer | Customer code |
| Type | Express type badge |
| Service | Service type badge |
| Status | Normalized status with icon |
| Problem | Problem indicator |
| Weight | Chargeable weight |
| Value | Package value |
| COD | Cash on delivery amount |
| Delivered | Delivery timestamp |
| Created | Creation timestamp |

### Filters

Available filters:

- **Status** - Filter by normalized tracking status
- **Express Type** - Domestic, Next Day, Fresh
- **Service Type** - Door to Door, Walk-In
- **Has Problem** - Show only problematic orders
- **Delivered** - Show only delivered orders
- **Pending** - Show only undelivered orders

### Search

Searchable fields:
- Order ID
- Tracking number
- Customer code
- Last status

### View Page

The order detail view displays:

- Order information (IDs, type, service)
- Sender address details
- Receiver address details
- Package information (weight, dimensions, value)
- Status and tracking history
- Request/response payloads (if enabled)

---

## Tracking Events Resource

View and filter tracking history.

### Columns

| Column | Description |
|--------|-------------|
| Order Ref | Related order reference |
| Tracking # | J&T tracking number |
| Status | Normalized tracking status badge (rendered from the raw scan type) |
| Scan Time | When the scan occurred |
| Location | Scan network name |
| Description | Event description |
| Problem | Problem indicator |
| City | Scan network city (hidden by default) |
| Staff | Scanning staff name (hidden by default) |
| Created | Record creation timestamp (hidden by default) |

### Filters

- **Status** - Filter by normalized tracking status
- **Has Problem** - Show only scans flagged with a problem
- **Delivered** - Show only scans with a delivered status

---

## Webhook Logs Resource

Monitor webhook activity and troubleshoot issues.

### Columns

| Column | Description |
|--------|-------------|
| Bill Code | J&T tracking number |
| Order ID | Related order reference |
| Processed | Processing status badge |
| Exception | Error message when processing failed |
| Processed At | When the webhook was processed |
| Created | When received |

### Features

- Open a webhook log to see its raw JSON payload (requires `filament-jnt.features.show_raw_payloads`)
- Filter by processing status
- Identify failed webhooks
- Debug webhook issues

---

## Actions

### Cancel Order Action

Cancel a shipping order from the view page:

1. Click **Cancel Order** button
2. Select cancellation reason from grouped options
3. Add custom reason if "Other" selected
4. Confirm cancellation

**Reason Categories**:
- Customer-Initiated (changed mind, wrong item, etc.)
- Merchant-Initiated (out of stock, price error, etc.)
- Delivery Issues (wrong address, recipient unavailable)
- Payment Issues (payment failed, fraud suspected)
- Other (system error, custom reason)

**Visibility**: Only shown for cancellable orders (hidden once delivered, cancelled, or returned).

### Sync Tracking Action

Manually sync tracking information:

1. Click **Sync Tracking** button
2. Confirm the sync action
3. Wait for API response
4. View updated status

**Features**:
- Fetches latest tracking from J&T API
- Updates order status
- Creates new tracking events
- Shows success/error notification

**Visibility**: Only shown for orders with tracking number.

### Print AWB Action

Print the Air Waybill (shipping label) for an order from the order view page:

1. Open the order and click **Print AWB**
2. Wait for PDF generation
3. Label opens in a new browser tab

**Features**:
- Generates shipping label via J&T API
- Opens PDF in new browser window
- Shows error notification if generation fails

**Visibility**: Available on any order that already has an `order_id`.

There is no bulk print action. `PrintAwbTableAction` prints one label per order.

---

## Dashboard Widget

The JntStatsWidget displays shipping statistics on your dashboard.

### Stats Displayed

| Stat | Description | Color |
|------|-------------|-------|
| Total Orders | All shipping orders | Primary |
| Delivered | Orders with delivery date | Success |
| In Transit | On the way | Info |
| Pending | Awaiting pickup | Warning |
| Returns | Being returned | Purple |
| Problems | Requires attention | Danger |

### Features

- **Caching**: Stats cached for 30 seconds (hard limit 90)
- **Owner-scoped**: Filtered by the owner resolved from `commerce-support`
- **Responsive**: 6-column layout
- **Icons**: Heroicons for visual clarity

### Widget Placement

The widget appears on the dashboard by default. To customize placement:

```php
// In your panel provider
->widgets([
    // Your custom widgets order
    AccountWidget::class,
    JntStatsWidget::class,
])
```

---

## Customization

`JntOrderResource`, `JntTrackingEventResource`, `JntWebhookLogResource`, and
`JntStatsWidget` are all declared `final`, so they cannot be subclassed. Write your own
resource and widget classes instead, and register them on the panel.

### Custom Resource

`BaseJntResource` is the abstract base and only requires a `navigationSortKey()`:

```php
use AIArmada\FilamentJnt\Resources\BaseJntResource;

class CustomOrderResource extends BaseJntResource
{
    protected static ?string $model = \App\Models\CustomJntOrder::class;

    protected static function navigationSortKey(): string
    {
        return 'orders';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // ... your own columns
            ]);
    }
}
```

Register it on the panel and keep the J&T features you want:

```php
use AIArmada\FilamentJnt\FilamentJntPlugin;

$panel->plugins([
    FilamentJntPlugin::make()->webhookLogs(false),
]);

$panel->resources([
    \App\Filament\Resources\CustomOrderResource::class,
]);
```

### Custom Widget

Extend Filament's own widget base and pull the shared aggregator:

```php
use AIArmada\FilamentJnt\Support\JntStatsAggregator;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $stats = JntStatsAggregator::calculateOrderStats();

        return [
            Stat::make('Total Orders', $stats['total']),
            Stat::make('Delivered', $stats['delivered']),
        ];
    }
}
```

---

## Best Practices

### Performance

1. **Enable polling appropriately** - Use longer intervals for low-traffic panels
2. **Cache configuration** - Run `php artisan config:cache` in production
3. **Widget caching** - Stats are cached for 30 seconds (hard limit 90) automatically

### Security

1. **Owner scoping** - Enable for multi-tenant applications; scoping comes from `commerce-support`, not Filament tenancy
2. **Action authorization** - Actions check authentication
3. **ID validation** - Actions validate record ownership

### UX

1. **Filters above content** - Easy access to filtering
2. **Copyable fields** - Order ID and tracking number are copyable
3. **Polling** - Tables auto-refresh for live updates
4. **Notifications** - Actions provide success/error feedback
