# Shipping Vision: Filament Enhancements

> **Document:** 10 of 11  
> **Package:** `aiarmada/filament-shipping`  
> **Status:** Vision Document  
> **Last Updated:** December 7, 2025

---

## Overview

The `aiarmada/filament-shipping` package provides a comprehensive admin interface for shipping management, including shipment tracking, zone configuration, carrier settings, and dashboard analytics.

---

## Resources

### ShipmentResource

Full CRUD for shipments with rich features:

**Table Features:**
- Status badges with icons and colors
- Carrier logos
- Tracking number quick copy
- Bulk actions (ship, print labels, cancel)
- Filters by status, carrier, date range

**View Page:**
- Shipment details infolist
- Tracking timeline visualization
- Label preview and reprint
- Status change actions

**Actions:**
- `CreateShipmentAction` - Create from order
- `ShipAction` - Submit to carrier
- `PrintLabelAction` - Generate/print label
- `CancelShipmentAction` - Cancel with reason
- `SyncTrackingAction` - Manual tracking refresh

### ShippingZoneResource

Geographic zone configuration:

**Features:**
- Visual zone builder
- Postcode range editor
- Rate table inline editing
- Carrier availability toggles
- Zone testing (check if address matches)

### ShippingRateResource

Rate table management:

**Features:**
- Grouped by zone
- Calculation type presets
- Free shipping threshold
- Weight/price bracket tables
- Bulk import/export

### CarrierSettingsResource

Per-carrier configuration:

**Features:**
- API credentials (encrypted)
- Default options
- Label format settings
- Enabled/disabled toggle
- Connection test action

### ReturnAuthorizationResource

RMA management:

**Features:**
- Pending approvals queue
- Item inspection form
- Return label generation button
- Refund processing integration
- Status workflow actions

---

## Widgets

### ShippingDashboardWidget

Main dashboard stats:

```php
class ShippingDashboardWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Pending Shipments', $this->getPendingCount())
                ->icon('heroicon-o-clock')
                ->color('warning'),
            
            Stat::make('In Transit', $this->getInTransitCount())
                ->icon('heroicon-o-truck')
                ->color('info'),
            
            Stat::make('Delivered Today', $this->getDeliveredTodayCount())
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            
            Stat::make('Exceptions', $this->getExceptionsCount())
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
            
            Stat::make('Pending Returns', $this->getPendingReturnsCount())
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning'),
        ];
    }
}
```

### CarrierPerformanceWidget

Per-carrier analytics:

```php
class CarrierPerformanceWidget extends ChartWidget
{
    // Delivery success rate by carrier
    // Average delivery time by carrier
    // Exception rate by carrier
}
```

### ShipmentMapWidget

Geographic visualization:

```php
class ShipmentMapWidget extends Widget
{
    // Active shipments on map
    // Delivery concentration heatmap
    // Zone coverage visualization
}
```

### PendingActionsWidget

Action queue:

```php
class PendingActionsWidget extends Widget
{
    // Orders needing shipment
    // Shipments ready to ship
    // Returns awaiting approval
    // Exceptions needing attention
}
```

---

## Bulk Operations

### Bulk Ship Action

```php
class BulkShipAction extends BulkAction
{
    protected function setUp(): void
    {
        $this->label('Ship Selected')
            ->icon('heroicon-o-paper-airplane')
            ->requiresConfirmation()
            ->action(function (Collection $records) {
                $service = app(BulkShipmentService::class);
                $result = $service->shipBatch($records->pluck('id')->toArray());
                
                Notification::make()
                    ->title("Shipped {$result->successCount} of {$result->totalCount}")
                    ->success()
                    ->send();
            });
    }
}
```

### Bulk Print Labels Action

```php
class BulkPrintLabelsAction extends BulkAction
{
    protected function setUp(): void
    {
        $this->label('Print Labels')
            ->icon('heroicon-o-printer')
            ->action(function (Collection $records) {
                $pdf = app(LabelPrintService::class)
                    ->generateCombinedPdf($records);
                
                return response()->streamDownload(
                    fn () => print($pdf),
                    'shipping-labels.pdf'
                );
            });
    }
}
```

---

## Pages

### ShippingDashboard

Custom dashboard page:

```php
class ShippingDashboard extends Page
{
    protected static string $view = 'filament-shipping::pages.dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            ShippingDashboardWidget::class,
            PendingActionsWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            CarrierPerformanceWidget::class,
            RecentShipmentsWidget::class,
        ];
    }
}
```

### ManifestPage

End-of-day manifest generation:

```php
class ManifestPage extends Page
{
    // Select carrier
    // Select date
    // Generate manifest PDF
    // Mark shipments as picked up
}
```

---

## Integration with Other Filament Packages

### Cart Bridge

Link to `filament-cart`:

```php
class CartBridge
{
    public static function getOrderLink($orderId): ?string
    {
        if (class_exists(OrderResource::class)) {
            return OrderResource::getUrl('view', ['record' => $orderId]);
        }
        return null;
    }
}
```

### Inventory Bridge

Link to `filament-inventory`:

```php
class InventoryBridge
{
    public static function getWarehouseLink($warehouseId): ?string;
    public static function getStockLocationOptions(): array;
}
```

---

## Notifications

### Shipment Status Notifications

```php
Filament::registerRenderHook(
    'panels::body.end',
    fn () => Livewire::mount('shipping-status-listener')
);

class ShippingStatusListener extends Component
{
    protected $listeners = [
        'echo:shipping,ShipmentException' => 'notifyException',
    ];

    public function notifyException($data)
    {
        Notification::make()
            ->title('Shipment Exception')
            ->body("Shipment {$data['tracking_number']} has an exception")
            ->danger()
            ->send();
    }
}
```

---

## Navigation

**Previous:** [09-database-schema.md](09-database-schema.md)  
**Next:** [11-implementation-roadmap.md](11-implementation-roadmap.md)
