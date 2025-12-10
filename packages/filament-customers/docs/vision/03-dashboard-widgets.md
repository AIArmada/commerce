# Dashboard Widgets

> **Document:** 03 of 03  
> **Package:** `aiarmada/filament-customers`  
> **Status:** Vision

---

## Customer Stats Widget

```php
namespace AIArmada\FilamentCustomers\Widgets;

class CustomerStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Customers', Customer::count())
                ->chart($this->getGrowthChart()),
            Stat::make('New This Month', Customer::whereMonth('created_at', now()->month)->count())
                ->color('success'),
            Stat::make('Active (90 Days)', Customer::where('last_order_at', '>=', now()->subDays(90))->count())
                ->color('primary'),
            Stat::make('At Risk', Customer::where('last_order_at', '<', now()->subDays(90))
                ->whereNotNull('last_order_at')
                ->count())
                ->color('danger'),
        ];
    }
}
```

---

## Segment Distribution Widget

```php
class SegmentDistributionWidget extends ChartWidget
{
    protected static ?string $heading = 'Customer Segments';

    protected function getData(): array
    {
        $segments = Segment::withCount('customers')
            ->orderBy('customers_count', 'desc')
            ->limit(8)
            ->get();

        return [
            'datasets' => [[
                'data' => $segments->pluck('customers_count')->toArray(),
                'backgroundColor' => ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#6366f1', '#ec4899', '#14b8a6', '#f97316'],
            ]],
            'labels' => $segments->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
```

---

## Top Customers Widget

```php
class TopCustomersWidget extends TableWidget
{
    protected static ?string $heading = 'Top Customers (Lifetime Value)';

    protected function getTableQuery(): Builder
    {
        return Customer::query()
            ->orderBy('total_spent', 'desc')
            ->limit(10);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('full_name')
                ->url(fn ($record) => CustomerResource::getUrl('view', ['record' => $record])),
            TextColumn::make('email'),
            TextColumn::make('orders_count')->label('Orders'),
            TextColumn::make('total_spent')
                ->money('MYR')
                ->weight(FontWeight::Bold),
        ];
    }
}
```

---

## Customer Growth Chart

```php
class CustomerGrowthWidget extends ChartWidget
{
    protected static ?string $heading = 'New Customers (Last 12 Months)';

    protected function getData(): array
    {
        $data = Customer::query()
            ->where('created_at', '>=', now()->subMonths(12))
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month');

        return [
            'datasets' => [[
                'label' => 'New Customers',
                'data' => $data->values()->toArray(),
                'borderColor' => '#10b981',
            ]],
            'labels' => $data->keys()->map(fn ($m) => Carbon::parse($m)->format('M Y'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
```

---

## Navigation

**Previous:** [02-customer-resource.md](02-customer-resource.md)  
**Back to:** [01-executive-summary.md](01-executive-summary.md)
