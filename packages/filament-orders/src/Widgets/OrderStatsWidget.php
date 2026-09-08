<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Widgets;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\FilamentOrders\Support\FilamentOrdersCache;
use AIArmada\Orders\Models\Order;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;

final class OrderStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null && Gate::forUser($user)->allows('viewAny', Order::class);
    }

    protected function getStats(): array
    {
        $includeGlobal = (bool) config('orders.owner.include_global', false);
        $computed = FilamentOrdersCache::rememberStats($includeGlobal);

        $todayOrders = $computed['todayOrders'];
        $yesterdayOrders = $computed['yesterdayOrders'];
        $todayChange = $yesterdayOrders > 0
            ? round((($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100)
            : 0;

        $todayRevenue = $computed['todayRevenue'];
        $yesterdayRevenue = $computed['yesterdayRevenue'];
        $revenueChange = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100)
            : 0;

        $pendingOrders = $computed['pendingOrders'];

        $monthlyRevenue = $computed['monthlyRevenue'];
        $lastMonthRevenue = $computed['lastMonthRevenue'];
        $monthlyChange = $lastMonthRevenue > 0
            ? round((($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100)
            : 0;

        $currency = (string) config('orders.currency.default', 'MYR');

        return [
            Stat::make('Today\'s Orders', number_format($todayOrders))
                ->description($todayChange >= 0 ? "{$todayChange}% increase" : abs($todayChange) . '% decrease')
                ->descriptionIcon($todayChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($todayChange >= 0 ? 'success' : 'danger')
                ->chart([7, 3, 4, 5, 6, $todayOrders]),

            Stat::make('Today\'s Revenue', MoneyFormatter::formatMinor($todayRevenue, $currency))
                ->description($revenueChange >= 0 ? "{$revenueChange}% increase" : abs($revenueChange) . '% decrease')
                ->descriptionIcon($revenueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueChange >= 0 ? 'success' : 'danger'),

            Stat::make('Pending Orders', number_format($pendingOrders))
                ->description('Awaiting action')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingOrders > 10 ? 'warning' : 'gray'),

            Stat::make('Monthly Revenue', MoneyFormatter::formatMinor($monthlyRevenue, $currency))
                ->description($monthlyChange >= 0 ? "{$monthlyChange}% vs last month" : abs($monthlyChange) . '% vs last month')
                ->descriptionIcon($monthlyChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($monthlyChange >= 0 ? 'success' : 'danger'),
        ];
    }
}
