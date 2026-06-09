<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\FilamentCart\Models\Cart;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class CartStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $base = Cart::query()->forOwner(includeGlobal: Cart::includeGlobalRecords());
        $recentCutoff = now()->subMinutes(30);
        $highValueThreshold = (int) config('filament-cart.analytics.high_value_threshold_minor', 10000);
        $yesterday = now()->subDay();

        $activeCarts = (clone $base)->where('last_activity_at', '>=', $recentCutoff)->count();
        $cartsWithItems = (clone $base)->where('items_count', '>', 0)->where('last_activity_at', '>=', $recentCutoff)->count();
        $checkoutsInProgress = (clone $base)->whereNotNull('checkout_started_at')->whereNull('checkout_abandoned_at')->count();
        $recentAbandonments = (clone $base)->where('checkout_abandoned_at', '>=', $recentCutoff)->count();

        $totalValue = (int) (clone $base)->where('items_count', '>', 0)->sum('total');
        $highValueCarts = (clone $base)->where('total', '>=', $highValueThreshold)->count();

        $checkoutsStarted24h = (clone $base)
            ->whereNotNull('checkout_started_at')
            ->where('checkout_started_at', '>=', $yesterday)
            ->count();

        $abandoned24h = (clone $base)
            ->whereNotNull('checkout_abandoned_at')
            ->where('checkout_abandoned_at', '>=', $yesterday)
            ->count();

        return [
            Stat::make('Active Carts', (string) $activeCarts)
                ->description("{$cartsWithItems} with items")
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->chart($this->getActiveCartsChart())
                ->color('primary'),

            Stat::make('Checkouts', (string) $checkoutsInProgress)
                ->description('In progress')
                ->descriptionIcon(Heroicon::OutlinedCreditCard)
                ->color('success'),

            Stat::make('Recent Abandonments', (string) $recentAbandonments)
                ->description($this->getAbandonmentRate($checkoutsStarted24h, $abandoned24h) . '% rate (24h)')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($recentAbandonments > 0 ? 'warning' : 'gray'),

            Stat::make('Total Value', $this->formatMoney($totalValue))
                ->description("{$highValueCarts} high-value carts")
                ->descriptionIcon(Heroicon::OutlinedCurrencyDollar)
                ->color('info'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }

    private function getAbandonmentRate(int $checkoutsStarted, int $abandoned): string
    {
        if ($checkoutsStarted === 0) {
            return '0';
        }

        return number_format(($abandoned / $checkoutsStarted) * 100, 1);
    }

    private function getActiveCartsChart(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Cart::query()->forOwner(includeGlobal: Cart::includeGlobalRecords())
                ->where('items_count', '>', 0)
                ->whereDate('updated_at', $date->toDateString())
                ->count();
            $data[] = $count;
        }

        return $data;
    }

    private function formatMoney(int $amount): string
    {
        return MoneyFormatter::formatMinor($amount, (string) config('cart.money.default_currency', 'USD'));
    }
}
