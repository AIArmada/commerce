<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Widgets;

use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\Promotion;
use AIArmada\Pricing\Support\PricingOwnerScope;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PricingStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $activePriceLists = PricingOwnerScope::applyToOwnedQuery(PriceList::query())
            ->active()
            ->count();

        $activePromotions = PricingOwnerScope::applyToOwnedQuery(Promotion::query())
            ->active()
            ->count();

        $totalPromotionUsage = PricingOwnerScope::applyToOwnedQuery(Promotion::query())
            ->sum('usage_count');

        return [
            Stat::make('Active Price Lists', number_format($activePriceLists))
                ->description('Currently active')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info'),

            Stat::make('Active Promotions', number_format($activePromotions))
                ->description('Running promotions')
                ->descriptionIcon('heroicon-m-gift')
                ->color('success'),

            Stat::make('Promotion Uses', number_format($totalPromotionUsage))
                ->description('Total redemptions')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('warning'),
        ];
    }
}
