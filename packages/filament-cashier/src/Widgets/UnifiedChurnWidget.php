<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Widgets;

use AIArmada\CashierChip\Cashier as CashierChip;
use AIArmada\FilamentCashier\Support\CashierOwnerScope;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Laravel\Cashier\Subscription;

final class UnifiedChurnWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        $detector = app(GatewayDetector::class);
        $canceledThisMonth = 0;
        $canceledLastMonth = 0;

        $startOfMonth = now()->startOfMonth();
        $startOfLastMonth = now()->subMonth()->startOfMonth();
        $endOfLastMonth = now()->subMonth()->endOfMonth();

        // Count Stripe cancellations
        if ($detector->isAvailable('stripe') && class_exists(Subscription::class)) {
            $canceledThisMonth += CashierOwnerScope::apply(Subscription::query())
                ->whereNotNull('ends_at')
                ->where('ends_at', '>=', $startOfMonth)
                ->count();

            $canceledLastMonth += CashierOwnerScope::apply(Subscription::query())
                ->whereNotNull('ends_at')
                ->whereBetween('ends_at', [$startOfLastMonth, $endOfLastMonth])
                ->count();
        }

        // Count CHIP cancellations
        if ($detector->isAvailable('chip')) {
            $subscriptionModel = CashierChip::$subscriptionModel;
            $canceledThisMonth += CashierOwnerScope::apply($subscriptionModel::query())
                ->whereNotNull('ends_at')
                ->where('ends_at', '>=', $startOfMonth)
                ->count();

            $canceledLastMonth += CashierOwnerScope::apply($subscriptionModel::query())
                ->whereNotNull('ends_at')
                ->whereBetween('ends_at', [$startOfLastMonth, $endOfLastMonth])
                ->count();
        }

        // Calculate trend
        $trend = $canceledLastMonth > 0
            ? round((($canceledThisMonth - $canceledLastMonth) / $canceledLastMonth) * 100)
            : 0;

        $trendIcon = $trend <= 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up';
        $trendColor = $trend <= 0 ? 'success' : 'danger';
        $trendDescription = $trend <= 0
            ? abs($trend) . '% less than last month'
            : $trend . '% more than last month';

        return [
            Stat::make(__('filament-cashier::dashboard.widgets.churn.label'), (string) $canceledThisMonth)
                ->description($trendDescription)
                ->descriptionIcon($trendIcon)
                ->color($trendColor)
                ->chart([5, 3, 4, 2, 3, $canceledLastMonth, $canceledThisMonth]),
        ];
    }
}
