<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\FilamentCart\Models\Cart;
use Akaunting\Money\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class CartStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Carts', Cart::count())
                ->description('All cart sessions')
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->color('primary'),

            Stat::make('Active Carts', Cart::notEmpty()->count())
                ->description('Carts with items')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('success'),

            Stat::make('Total Items', Cart::sum('quantity'))
                ->description('Across all carts')
                ->descriptionIcon(Heroicon::OutlinedShoppingBag)
                ->color('info'),

            Stat::make('Cart Value', $this->formatMoney((int) Cart::sum('subtotal')))
                ->description('Total potential revenue')
                ->descriptionIcon(Heroicon::OutlinedCurrencyDollar)
                ->color('warning'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }

    private function formatMoney(int $amount): string
    {
        $currency = mb_strtoupper(config('cart.money.default_currency', 'USD'));

        return (string) Money::{$currency}($amount);
    }
}
