<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Widgets;

use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use AIArmada\FilamentAffiliateNetwork\Support\NetworkStatsAggregator;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class NetworkStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return NetworkAdminAccess::allows();
    }

    protected function getStats(): array
    {
        /** @var array{activeSites: int, activeOffers: int, pendingApplications: int, totalClicks: int, totalConversions: int, totalRevenue: int, conversionRate: float, revenueFormatted: string} $aggregated */
        $aggregated = OwnerCache::remember(
            null,
            'affiliate-network.network-stats',
            now()->addSeconds(30),
            fn (): array => NetworkStatsAggregator::aggregate(),
        );

        return [
            Stat::make('Active Sites', number_format($aggregated['activeSites']))
                ->description('Verified merchant sites')
                ->icon('heroicon-o-globe-alt')
                ->color('success'),

            Stat::make('Active Offers', number_format($aggregated['activeOffers']))
                ->description('Live affiliate offers')
                ->icon('heroicon-o-gift')
                ->color('primary'),

            Stat::make('Pending Applications', number_format($aggregated['pendingApplications']))
                ->description('Awaiting review')
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('Total Clicks', number_format($aggregated['totalClicks']))
                ->description('Network-wide clicks')
                ->icon('heroicon-o-cursor-arrow-rays'),

            Stat::make('Conversion Rate', $aggregated['conversionRate'] . '%')
                ->description('Clicks to conversions')
                ->icon('heroicon-o-arrow-trending-up')
                ->color($aggregated['conversionRate'] > 5 ? 'success' : 'warning'),

            Stat::make('Total Revenue', $aggregated['revenueFormatted'])
                ->description('Tracked revenue')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
        ];
    }
}
