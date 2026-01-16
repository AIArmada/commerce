<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Widgets;

use AIArmada\FilamentPromotions\Models\Promotion;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PromotionStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    protected function getStats(): array
    {
        $totalPromotions = Promotion::count();
        $activePromotions = Promotion::where('is_active', true)->count();
        $codePromotions = Promotion::whereNotNull('code')->count();
        $autoPromotions = Promotion::whereNull('code')->where('is_active', true)->count();

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
