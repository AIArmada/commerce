<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Widgets;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class NetworkStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // These are intentionally network-wide (cross-tenant) admin stats.
        // Explicit global context is required to bypass per-tenant owner scoping.
        return OwnerContext::withOwner(null, function (): array {
            $totalClicks = AffiliateOfferLink::withoutGlobalScope('owner_via_affiliate')->sum('clicks');
            $totalConversions = AffiliateOfferLink::withoutGlobalScope('owner_via_affiliate')->sum('conversions');
            $totalRevenue = AffiliateOfferLink::withoutGlobalScope('owner_via_affiliate')->sum('revenue');

            $conversionRate = $totalClicks > 0
                ? round(($totalConversions / $totalClicks) * 100, 2)
                : 0;

            return [
                Stat::make('Active Sites', AffiliateSite::query()->withoutOwnerScope()->where('status', AffiliateSite::STATUS_VERIFIED)->count())
                    ->description('Verified merchant sites')
                    ->icon('heroicon-o-globe-alt')
                    ->color('success'),

                Stat::make('Active Offers', AffiliateOffer::withoutGlobalScope('owner_via_site')->where('status', AffiliateOffer::STATUS_ACTIVE)->count())
                    ->description('Live affiliate offers')
                    ->icon('heroicon-o-gift')
                    ->color('primary'),

                Stat::make('Pending Applications', AffiliateOfferApplication::withoutGlobalScope('owner_via_affiliate')->where('status', AffiliateOfferApplication::STATUS_PENDING)->count())
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
        });
    }
}
