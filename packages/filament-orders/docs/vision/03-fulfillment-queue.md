# Fulfillment Queue

> **Document:** 03 of 04  
> **Package:** `aiarmada/filament-orders`  
> **Status:** Vision

---

## Overview

The Fulfillment Queue is a dedicated page for warehouse staff to process orders ready for shipping.

---

## Fulfillment Queue Page

```php
namespace AIArmada\FilamentOrders\Pages;

class FulfillmentQueue extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationGroup = 'Sales';
    protected static ?string $navigationLabel = 'Fulfillment';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament-orders::pages.fulfillment-queue';

    public function getViewData(): array
    {
        return [
            'pendingOrders' => $this->getPendingOrders(),
            'todayStats' => $this->getTodayStats(),
        ];
    }

    protected function getPendingOrders()
    {
        return Order::query()
            ->whereState('status', [Processing::class])
            ->with(['items', 'shippingAddress', 'customer'])
            ->orderBy('created_at')
            ->paginate(20);
    }

    protected function getTodayStats(): array
    {
        return [
            'pending' => Order::whereState('status', Processing::class)->count(),
            'shipped_today' => Order::whereDate('shipped_at', today())->count(),
            'avg_fulfillment_time' => $this->calculateAvgFulfillmentTime(),
        ];
    }
}
```

---

## Queue Table Component

```php
class FulfillmentQueueTable extends TableWidget
{
    protected function getTableQuery(): Builder
    {
        return Order::query()
            ->whereState('status', Processing::class)
            ->orderBy('created_at');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('order_number')
                ->copyable(),
            TextColumn::make('customer.full_name'),
            TextColumn::make('shippingAddress.city'),
            TextColumn::make('items_count')
                ->counts('items'),
            TextColumn::make('created_at')
                ->since()
                ->description(fn ($record) => 
                    $record->created_at->diffInHours(now()) > 24 
                        ? '⚠️ Overdue' 
                        : null
                ),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('view')
                ->url(fn ($record) => OrderResource::getUrl('view', ['record' => $record])),
            Action::make('quick_ship')
                ->icon('heroicon-o-truck')
                ->form([
                    Select::make('carrier')
                        ->options(Carrier::pluck('name', 'code'))
                        ->required(),
                    TextInput::make('tracking_number')
                        ->required(),
                ])
                ->action(function (Order $record, array $data) {
                    $record->status->transitionTo(Shipped::class, $data);
                }),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            BulkAction::make('bulk_ship')
                ->label('Bulk Ship')
                ->form([
                    Select::make('carrier')
                        ->options(Carrier::pluck('name', 'code'))
                        ->required(),
                ])
                ->action(function (Collection $records, array $data) {
                    // Generate labels in bulk
                    foreach ($records as $order) {
                        $tracking = app(ShippingService::class)
                            ->generateLabel($order, $data['carrier']);
                        $order->status->transitionTo(Shipped::class, [
                            'carrier' => $data['carrier'],
                            'tracking_number' => $tracking,
                        ]);
                    }
                }),
            BulkAction::make('print_packing_slips')
                ->label('Print Packing Slips')
                ->action(function (Collection $records) {
                    return response()->streamDownload(function () use ($records) {
                        echo app(PackingSlipGenerator::class)->generateBatch($records);
                    }, 'packing-slips.pdf');
                }),
        ];
    }
}
```

---

## Fulfillment Workflow

```
┌────────────────────────────────────────────────────────────────┐
│ FULFILLMENT QUEUE                                              │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ Today: 12 Pending │ 45 Shipped │ Avg Time: 2.4 hrs            │
│                                                                 │
│ ☐ ORD2412-00156 │ John Doe      │ KL    │ 3 items │ 2h ago   [Ship]
│ ☐ ORD2412-00157 │ Jane Smith    │ PJ    │ 1 item  │ 1h ago   [Ship]
│ ☐ ORD2412-00158 │ Bob Wilson    │ Shah  │ 5 items │ 30m ago  [Ship]
│ ☐ ORD2412-00159 │ Alice Chen    │ Ampang│ 2 items │ 15m ago  [Ship]
│                                                                 │
│ [ ] Select All        [Bulk Ship] [Print Packing Slips]        │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

---

## Navigation

**Previous:** [02-order-resource.md](02-order-resource.md)  
**Next:** [04-dashboard-widgets.md](04-dashboard-widgets.md)
