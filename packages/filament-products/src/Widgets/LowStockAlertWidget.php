<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Widgets;

use AIArmada\Products\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class LowStockAlertWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        // Get products with low stock (assuming track_inventory and stock_quantity exist)
        $lowStockCount = Product::query()
            ->where('track_inventory', true)
            ->where('stock_quantity', '<=', DB::raw('low_stock_threshold'))
            ->where('stock_quantity', '>', 0)
            ->count();

        $outOfStockCount = Product::query()
            ->where('track_inventory', true)
            ->where('stock_quantity', '<=', 0)
            ->count();

        $totalTracked = Product::query()
            ->where('track_inventory', true)
            ->count();

        return [
            Stat::make('Low Stock Products', $lowStockCount)
                ->description('Products below threshold')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->url(route('filament.admin.resources.products.index', [
                    'tableFilters' => [
                        'low_stock' => ['isActive' => true],
                    ],
                ])),

            Stat::make('Out of Stock', $outOfStockCount)
                ->description('Need restocking')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger')
                ->url(route('filament.admin.resources.products.index', [
                    'tableFilters' => [
                        'out_of_stock' => ['isActive' => true],
                    ],
                ])),

            Stat::make('Inventory Tracked', $totalTracked)
                ->description('Total products tracked')
                ->descriptionIcon('heroicon-o-cube')
                ->color('info'),
        ];
    }
}
