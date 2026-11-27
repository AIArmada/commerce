<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Widgets;

use AIArmada\Shipping\Features\Reporting\Api\DashboardStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ShippingDashboardWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $stats = app(DashboardStats::class)->getAllStats();

        return [
            Stat::make('Pending Shipments', $stats['pending'])
                ->description('Awaiting shipping')
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('In Transit', $stats['inTransit'])
                ->description('Currently shipping')
                ->icon('heroicon-o-truck')
                ->color('info'),

            Stat::make('Delivered Today', $stats['deliveredToday'])
                ->description('Successful deliveries')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Exceptions', $stats['exceptions'])
                ->description('Need attention')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),

            Stat::make('Pending Returns', $stats['pendingReturns'])
                ->description('Awaiting approval')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning'),
        ];
    }
}
