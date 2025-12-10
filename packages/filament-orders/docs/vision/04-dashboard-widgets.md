# Dashboard Widgets

> **Document:** 04 of 04  
> **Package:** `aiarmada/filament-orders`  
> **Status:** Vision

---

## Order Stats Widget

```php
namespace AIArmada\FilamentOrders\Widgets;

class OrderStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $today = Order::whereDate('created_at', today());
        $thisMonth = Order::whereMonth('created_at', now()->month);

        return [
            Stat::make('Orders Today', $today->count())
                ->description($this->formatMoney($today->sum('grand_total')))
                ->chart($this->getHourlyOrderChart())
                ->color('primary'),
            Stat::make('Pending Fulfillment', Order::whereState('status', Processing::class)->count())
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('This Month', $thisMonth->count())
                ->description($this->formatMoney($thisMonth->sum('grand_total')))
                ->color('success'),
            Stat::make('Average Order Value', $this->formatMoney($thisMonth->avg('grand_total')))
                ->icon('heroicon-o-calculator'),
        ];
    }
}
```

---

## Order Status Distribution

```php
class OrderStatusDistributionWidget extends ChartWidget
{
    protected static ?string $heading = 'Order Status';
    protected static ?string $maxHeight = '250px';

    protected function getData(): array
    {
        $statuses = Order::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'datasets' => [
                [
                    'data' => $statuses->values()->toArray(),
                    'backgroundColor' => $statuses->keys()->map(fn ($status) => 
                        OrderStatus::from($status)->getChartColor()
                    )->toArray(),
                ],
            ],
            'labels' => $statuses->keys()->map(fn ($status) => 
                OrderStatus::from($status)->getLabel()
            )->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
```

---

## Revenue Chart

```php
class RevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue (Last 30 Days)';

    protected function getData(): array
    {
        $data = Order::query()
            ->whereState('status', [Completed::class, Delivered::class])
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, SUM(grand_total) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        // Fill in missing dates
        $period = CarbonPeriod::create(now()->subDays(30), now());
        $filledData = collect();
        
        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $filledData[$key] = $data[$key] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $filledData->values()->map(fn ($v) => $v / 100)->toArray(),
                    'borderColor' => '#10b981',
                    'fill' => true,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
            ],
            'labels' => $filledData->keys()->map(fn ($d) => 
                Carbon::parse($d)->format('M d')
            )->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
```

---

## Recent Orders Widget

```php
class RecentOrdersWidget extends TableWidget
{
    protected static ?string $heading = 'Recent Orders';
    protected int $defaultPaginationPageOption = 5;

    protected function getTableQuery(): Builder
    {
        return Order::query()
            ->with(['customer'])
            ->latest()
            ->limit(10);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('order_number')
                ->url(fn ($record) => OrderResource::getUrl('view', ['record' => $record])),
            TextColumn::make('customer.full_name'),
            TextColumn::make('grand_total')->money('MYR'),
            TextColumn::make('status')
                ->badge()
                ->color(fn ($state) => $state->color()),
            TextColumn::make('created_at')->since(),
        ];
    }
}
```

---

## Order Timeline Widget

```php
class OrderTimelineWidget extends Widget
{
    protected static string $view = 'filament-orders::widgets.order-timeline';

    public Order $order;

    protected function getViewData(): array
    {
        return [
            'events' => $this->order->history()
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ];
    }
}
```

Timeline view:
```blade
<div class="space-y-4">
    @foreach($events as $event)
        <div class="flex gap-4">
            <div class="w-8 h-8 rounded-full bg-{{ $event->event->color() }}-100 flex items-center justify-center">
                <x-heroicon-o-{{ $event->event->icon() }} class="w-4 h-4 text-{{ $event->event->color() }}-600" />
            </div>
            <div>
                <p class="font-medium">{{ $event->description }}</p>
                <p class="text-sm text-gray-500">
                    {{ $event->created_at->diffForHumans() }}
                    @if($event->user)
                        by {{ $event->user->name }}
                    @endif
                </p>
            </div>
        </div>
    @endforeach
</div>
```

---

## Navigation

**Previous:** [03-fulfillment-queue.md](03-fulfillment-queue.md)  
**Back to:** [01-executive-summary.md](01-executive-summary.md)
