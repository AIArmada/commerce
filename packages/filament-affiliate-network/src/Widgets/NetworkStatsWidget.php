<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Widgets;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class NetworkStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalClicks = AffiliateOfferLink::sum('clicks');
        $totalConversions = AffiliateOfferLink::sum('conversions');
        $totalRevenue = AffiliateOfferLink::sum('revenue');

        $conversionRate = $totalClicks > 0
            ? round(($totalConversions / $totalClicks) * 100, 2)
            : 0;

        return [
            Stat::make('Active Sites', AffiliateSite::where('status', AffiliateSite::STATUS_VERIFIED)->count())
                ->description('Verified merchant sites')
                ->icon('heroicon-o-globe-alt')
                ->color('success'),

            Stat::make('Active Offers', AffiliateOffer::where('status', AffiliateOffer::STATUS_ACTIVE)->count())
                ->description('Live affiliate offers')
                ->icon('heroicon-o-gift')
                ->color('primary'),

            Stat::make('Pending Applications', AffiliateOfferApplication::where('status', AffiliateOfferApplication::STATUS_PENDING)->count())
                ->description('Awaiting review')
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('Total Clicks', number_format($totalClicks))
                ->description('Network-wide clicks')
                ->icon('heroicon-o-cursor-arrow-rays'),

            Stat::make('Conversion Rate', $conversionRate . '%')
                ->description('Clicks to conversions')
                ->icon('heroicon-o-arrow-trending-up')
                ->color($conversionRate > 5 ? 'success' : 'warning'),

            Stat::make('Total Revenue', '$' . number_format($totalRevenue / 100, 2))
                ->description('Tracked revenue')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
        ];
    }
}
