<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Widgets;

use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Support\CustomersOwnerScope;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomerStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $baseQuery = CustomersOwnerScope::applyToOwnedQuery(Customer::query());

        $totalCustomers = (clone $baseQuery)->count();
        $activeCustomers = (clone $baseQuery)->where('status', CustomerStatus::Active)->count();
        $newThisMonth = (clone $baseQuery)->where('created_at', '>=', now()->startOfMonth())->count();
        $acceptsMarketing = (clone $baseQuery)->where('accepts_marketing', true)->count();

        // Calculate LTV
        $totalLtv = (clone $baseQuery)->sum('lifetime_value');
        $avgLtv = $totalCustomers > 0 ? (int) ($totalLtv / $totalCustomers) : 0;

        // Trend - new customers this week vs last week
        $thisWeek = (clone $baseQuery)->where('created_at', '>=', now()->subWeek())->count();
        $lastWeek = (clone $baseQuery)->whereBetween('created_at', [now()->subWeeks(2), now()->subWeek()])->count();

        $trend = $lastWeek > 0
            ? round((($thisWeek - $lastWeek) / $lastWeek) * 100)
            : ($thisWeek > 0 ? 100 : 0);

        $trendDescription = $trend >= 0 ? "{$trend}% increase" : abs($trend) . '% decrease';
        $trendIcon = $trend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $trendColor = $trend >= 0 ? 'success' : 'danger';

        return [
            Stat::make('Total Customers', number_format($totalCustomers))
                ->description($trendDescription . ' vs last week')
                ->descriptionIcon($trendIcon)
                ->color($trendColor)
                ->chart([$lastWeek, $thisWeek]),

            Stat::make('New This Month', number_format($newThisMonth))
                ->description('Customers joined')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info'),

            Stat::make('Active Customers', number_format($activeCustomers))
                ->description('Currently active')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Average LTV', 'RM ' . number_format($avgLtv / 100, 2))
                ->description('Per customer')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Marketing Opt-In', number_format($acceptsMarketing))
                ->description(round(($acceptsMarketing / max($totalCustomers, 1)) * 100) . '% opt-in rate')
                ->descriptionIcon('heroicon-m-envelope')
                ->color('warning'),
        ];
    }
}
