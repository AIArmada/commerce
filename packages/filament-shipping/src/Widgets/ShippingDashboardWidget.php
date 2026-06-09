<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Widgets;

use AIArmada\FilamentShipping\Support\ShippingStatsAggregator;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ShippingDashboardWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $aggregator = app(ShippingStatsAggregator::class);

        return [
            Stat::make('Pending Shipments', $aggregator->getPendingCount())
                ->description('Awaiting shipping')
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('In Transit', $aggregator->getInTransitCount())
                ->description('Currently shipping')
                ->icon('heroicon-o-truck')
                ->color('info'),

            Stat::make('Delivered Today', $aggregator->getDeliveredTodayCount())
                ->description('Successful deliveries')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Exceptions', $aggregator->getExceptionsCount())
                ->description('Need attention')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),

            Stat::make('Pending Returns', $aggregator->getPendingReturnsCount())
                ->description('Awaiting approval')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning'),
        ];
    }
}
