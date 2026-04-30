<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Widgets;

use AIArmada\FilamentPromotions\Models\Promotion;
use AIArmada\Promotions\Support\PromotionsOwnerScope;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class PromotionStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    protected function getStats(): array
    {
        $baseQuery = PromotionsOwnerScope::applyToOwnedQuery(Promotion::query());

        $totalPromotions = (clone $baseQuery)->count();
        $activePromotions = (clone $baseQuery)->where('is_active', true)->count();
        $codePromotions = (clone $baseQuery)->whereNotNull('code')->count();
        $autoPromotions = (clone $baseQuery)->whereNull('code')->where('is_active', true)->count();

        return [
            Stat::make('Total Promotions', (string) $totalPromotions)
                ->description('All promotions')
                ->color('gray')
                ->icon('heroicon-o-sparkles'),

            Stat::make('Active', (string) $activePromotions)
                ->description('Currently active')
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Promo Codes', (string) $codePromotions)
                ->description('Code-based promotions')
                ->color('primary')
                ->icon('heroicon-o-ticket'),

            Stat::make('Automatic', (string) $autoPromotions)
                ->description('Auto-applying promotions')
                ->color('info')
                ->icon('heroicon-o-bolt'),
        ];
    }
}
