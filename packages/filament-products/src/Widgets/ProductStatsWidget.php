<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Widgets;

use AIArmada\FilamentProducts\Support\ProductStatsAggregator;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class ProductStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $aggregator = app(ProductStatsAggregator::class);
        $stats = $aggregator->getStats();

        $trendDescription = $stats['trend'] >= 0 ? "{$stats['trend']}% increase" : abs($stats['trend']) . '% decrease';
        $trendIcon = $stats['trend'] >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $trendColor = $stats['trend'] >= 0 ? 'success' : 'danger';

        return [
            Stat::make('Total Products', number_format($stats['totalProducts']))
                ->description($trendDescription . ' from last week')
                ->descriptionIcon($trendIcon)
                ->color($trendColor)
                ->chart([$stats['previousWeekProducts'], $stats['lastWeekProducts']]),

            Stat::make('Active Products', number_format($stats['activeProducts']))
                ->description(round(($stats['activeProducts'] / max($stats['totalProducts'], 1)) * 100) . '% of total')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Draft Products', number_format($stats['draftProducts']))
                ->description('Awaiting publish')
                ->descriptionIcon('heroicon-m-pencil')
                ->color('warning'),

            Stat::make('Categories', number_format($stats['totalCategories']))
                ->description("{$stats['totalCollections']} collections")
                ->descriptionIcon('heroicon-m-folder')
                ->color('info'),
        ];
    }
}
