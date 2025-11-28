# Widgets

The Filament Stock plugin provides three widgets for your admin dashboard.

## StockStatsWidget

Displays a 5-column stats overview of stock activity.

### Usage

Register the widget in your Filament Panel:

```php
use AIArmada\FilamentStock\Widgets\StockStatsWidget;

public function panel(Panel $panel): Panel
{
    return $panel
        ->widgets([
            StockStatsWidget::class,
        ]);
}
```

### Statistics Displayed

| Stat | Description |
|------|-------------|
| Total Transactions | All-time transaction count |
| Inbound (30d) | Stock added in last 30 days |
| Outbound (30d) | Stock removed in last 30 days |
| Net Change (30d) | Inbound minus outbound for last 30 days |
| Active Reservations | Current non-expired reservations |

## StockTransactionTimelineWidget

Visual timeline of stock movements with color-coded entries.

### Dashboard Usage

```php
use AIArmada\FilamentStock\Widgets\StockTransactionTimelineWidget;

public function panel(Panel $panel): Panel
{
    return $panel
        ->widgets([
            StockTransactionTimelineWidget::class,
        ]);
}
```

### On a Stockable Record

Use this widget on a stockable model's view page to show only transactions for that record:

```php
use AIArmada\FilamentStock\Widgets\StockTransactionTimelineWidget;
use Filament\Resources\Pages\ViewRecord;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected function getFooterWidgets(): array
    {
        return [
            StockTransactionTimelineWidget::class,
        ];
    }
}
```

The widget automatically filters to the current record when a `$record` is available.

### Features

- **Color-coded entries** - Green for inbound, red for outbound
- **Transaction details** - Quantity, reason, user, notes
- **Summary stats** - Total in, out, and net change
- **Lazy loading** - Uses `#[Lazy]` attribute for performance
- **Time display** - Shows date, time, and relative time

### Icons by Reason

The widget uses contextual icons based on transaction reason:

**Inbound:**
- `restock` → `heroicon-o-arrow-down-tray`
- `return` → `heroicon-o-arrow-uturn-left`
- `adjustment` → `heroicon-o-adjustments-horizontal`

**Outbound:**
- `sale` → `heroicon-o-shopping-cart`
- `damaged` → `heroicon-o-exclamation-triangle`
- `expired` → `heroicon-o-clock`

## LowStockAlertsWidget

Table widget displaying items with low stock levels.

### Usage

```php
use AIArmada\FilamentStock\Widgets\LowStockAlertsWidget;

public function panel(Panel $panel): Panel
{
    return $panel
        ->widgets([
            LowStockAlertsWidget::class,
        ]);
}
```

### Features

- **Threshold-based** - Uses `low_stock_threshold` from config
- **Status badges** - Critical (0 stock) or Low
- **Auto-refresh** - Updates every 30 seconds
- **Filters** - By item type and status
- **Quick actions** - View history, Add stock

### Columns

| Column | Description |
|--------|-------------|
| Item Type | Stockable model class (shortened) |
| Item ID | ID with optional label lookup |
| Current Stock | Current level with color badge |
| Last Movement | Last transaction date |
| Status | Critical or Low badge |

### Getting Low Stock Count

Use the static method to get the count for custom badges:

```php
use AIArmada\FilamentStock\Widgets\LowStockAlertsWidget;

$count = LowStockAlertsWidget::getLowStockCount();
```

## Customizing Widget Column Span

All widgets support column span configuration:

```php
// Full width (default)
protected int|string|array $columnSpan = 'full';

// Half width
protected int|string|array $columnSpan = 1;

// Responsive
protected int|string|array $columnSpan = [
    'default' => 'full',
    'md' => 1,
    'lg' => 2,
];
```

## Widget Ordering

Set the `$sort` property to control dashboard order:

```php
// StockStatsWidget has sort = 1 (first)
// StockTransactionTimelineWidget has sort = 2
// LowStockAlertsWidget has sort = 3
```
