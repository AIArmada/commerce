<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\FilamentCart\Models\Cart;
use Akaunting\Money\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Real-time live statistics widget.
 */
class LiveStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '10s';

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $recentCutoff = now()->subMinutes(30);
        $highValueThreshold = (int) config('filament-cart.analytics.high_value_threshold_minor', 10000);

        $base = Cart::query()->forOwner(includeGlobal: Cart::includeGlobalRecords());
        $activeCarts = (clone $base)->where('last_activity_at', '>=', $recentCutoff)->count();
        $cartsWithItems = (clone $base)->where('items_count', '>', 0)->where('last_activity_at', '>=', $recentCutoff)->count();
        $checkoutsInProgress = (clone $base)->whereNotNull('checkout_started_at')->whereNull('checkout_abandoned_at')->count();
        $recentAbandonments = (clone $base)->where('checkout_abandoned_at', '>=', $recentCutoff)->count();
        $totalValue = (int) (clone $base)->where('items_count', '>', 0)->sum('total');
        $highValueCarts = (clone $base)->where('total', '>=', $highValueThreshold)->count();

        return [
            Stat::make('Active Carts', (string) $activeCarts)
                ->description("{$cartsWithItems} with items")
                ->icon('heroicon-o-shopping-cart')
                ->color('primary'),

            Stat::make('Checkouts', (string) $checkoutsInProgress)
                ->description('In progress')
                ->icon('heroicon-o-credit-card')
                ->color('success'),

            Stat::make('Recent Abandonments', (string) $recentAbandonments)
                ->description('Last 30 minutes')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($recentAbandonments > 0 ? 'warning' : 'gray'),

            Stat::make('Total Value', $this->formatMoney($totalValue))
                ->description("{$highValueCarts} high-value")
                ->icon('heroicon-o-currency-dollar')
                ->color('info'),
        ];
    }

    private function formatMoney(int $amount): string
    {
        $currency = mb_strtoupper(config('cart.money.default_currency', 'USD'));

        return (string) Money::{$currency}($amount);
    }
}
