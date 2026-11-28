<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Widgets;

use AIArmada\Cashier\Queries\BillingAnalyticsQuery;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class UnifiedChurnWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        $counts = app(BillingAnalyticsQuery::class)->cancellations();
        $canceledThisMonth = $counts['current'];
        $canceledLastMonth = $counts['previous'];

        // Calculate trend
        $trend = $canceledLastMonth > 0
            ? round((($canceledThisMonth - $canceledLastMonth) / $canceledLastMonth) * 100)
            : null;

        $trendIcon = $trend === null ? 'heroicon-m-minus' : ($trend <= 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up');
        $trendColor = $trend === null ? 'gray' : ($trend <= 0 ? 'success' : 'danger');
        $trendDescription = $trend === null
            ? 'No cancellations last month'
            : ($trend <= 0 ? abs($trend) . '% less than last month' : $trend . '% more than last month');

        return [
            Stat::make(__('filament-cashier::dashboard.widgets.churn.label'), (string) $canceledThisMonth)
                ->description($trendDescription)
                ->descriptionIcon($trendIcon)
                ->color($trendColor),
        ];
    }
}
