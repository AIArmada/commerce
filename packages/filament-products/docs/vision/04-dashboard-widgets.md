# Dashboard Widgets

> **Document:** 04 of 04  
> **Package:** `aiarmada/filament-products`  
> **Status:** Vision

---

## Product Stats Widget

```php
namespace AIArmada\FilamentProducts\Widgets;

class ProductStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Products', Product::count())
                ->icon('heroicon-o-cube')
                ->chart($this->getProductTrend()),
            Stat::make('Active Products', Product::active()->count())
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('Draft Products', Product::draft()->count())
                ->icon('heroicon-o-pencil')
                ->color('warning'),
            Stat::make('Out of Stock', $this->getOutOfStockCount())
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }

    protected function getOutOfStockCount(): int
    {
        if (!class_exists(\AIArmada\Inventory\Models\InventoryLevel::class)) {
            return 0;
        }

        return Product::whereHas('variants', function ($q) {
            $q->whereHas('inventoryLevels', fn ($i) => $i->where('quantity', 0));
        })->count();
    }
}
```

---

## Category Distribution Chart

```php
class CategoryDistributionWidget extends ChartWidget
{
    protected static ?string $heading = 'Products by Category';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $categories = Category::withCount('products')
            ->orderBy('products_count', 'desc')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Products',
                    'data' => $categories->pluck('products_count')->toArray(),
                    'backgroundColor' => [
                        '#3b82f6', '#ef4444', '#10b981', '#f59e0b',
                        '#6366f1', '#ec4899', '#14b8a6', '#f97316',
                        '#8b5cf6', '#06b6d4',
                    ],
                ],
            ],
            'labels' => $categories->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
```

---

## Low Stock Alert Widget

```php
class LowStockAlertWidget extends TableWidget
{
    protected static ?string $heading = 'Low Stock Products';
    protected int $defaultPaginationPageOption = 5;

    protected function getTableQuery(): Builder
    {
        return Product::query()
            ->whereHas('variants.inventoryLevels', function ($q) {
                $q->where('quantity', '<=', DB::raw('reorder_point'));
            })
            ->with(['variants.inventoryLevels'])
            ->limit(10);
    }

    protected function getTableColumns(): array
    {
        return [
            ImageColumn::make('featured_image'),
            TextColumn::make('name')
                ->limit(30),
            TextColumn::make('sku'),
            TextColumn::make('stock')
                ->getStateUsing(fn ($record) => 
                    $record->variants->sum(fn ($v) => $v->inventoryLevels->sum('quantity'))
                )
                ->color('danger'),
        ];
    }
}
```

---

## Recent Products Widget

```php
class RecentProductsWidget extends TableWidget
{
    protected static ?string $heading = 'Recently Added Products';

    protected function getTableQuery(): Builder
    {
        return Product::query()
            ->latest()
            ->limit(5);
    }

    protected function getTableColumns(): array
    {
        return [
            ImageColumn::make('featured_image')->circular(),
            TextColumn::make('name'),
            TextColumn::make('status')->badge(),
            TextColumn::make('created_at')
                ->since(),
        ];
    }
}
```

---

## Navigation

**Previous:** [03-category-resource.md](03-category-resource.md)  
**Back to:** [01-executive-summary.md](01-executive-summary.md)
