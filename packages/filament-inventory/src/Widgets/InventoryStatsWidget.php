<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Widgets;

use AIArmada\FilamentInventory\Services\InventoryStatsAggregator;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class InventoryStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        return config('filament-inventory.features.stats_widget', true);
    }

    protected function getStats(): array
    {
        $aggregator = app(InventoryStatsAggregator::class);
        $stats = $aggregator->getOverviewStats();

        return [
            Stat::make('Active Locations', number_format($stats['active_locations']))
                ->description('Warehouse locations')
                ->icon('heroicon-o-building-office-2')
                ->color('primary'),

            Stat::make('Total SKUs', number_format($stats['total_skus']))
                ->description('Unique products tracked')
                ->icon('heroicon-o-cube')
                ->color('info'),

            Stat::make('Total On Hand', number_format($stats['total_on_hand']))
                ->description('Physical stock')
                ->icon('heroicon-o-inbox-stack')
                ->color('success'),

            Stat::make('Total Reserved', number_format($stats['total_reserved']))
                ->description('Allocated to orders')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning'),

            Stat::make('Total Available', number_format($stats['total_available']))
                ->description('Ready to sell')
                ->icon('heroicon-o-shopping-cart')
                ->color($stats['total_available'] > 0 ? 'success' : 'danger'),

            Stat::make('Low Stock Items', number_format($stats['low_stock_count']))
                ->description('Below reorder point')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($stats['low_stock_count'] > 0 ? 'danger' : 'success'),
        ];
    }
}
