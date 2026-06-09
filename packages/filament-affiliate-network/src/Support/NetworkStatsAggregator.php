<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Support;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerContext;

final class NetworkStatsAggregator
{
    /**
     * @return array{activeSites: int, activeOffers: int, pendingApplications: int, totalClicks: int, totalConversions: int, totalRevenue: int, conversionRate: float, revenueFormatted: string}
     */
    public static function aggregate(): array
    {
        return OwnerContext::withOwner(null, function (): array {
            $totalClicks = AffiliateOfferLink::withoutGlobalScope('owner_via_affiliate')->sum('clicks');
            $totalConversions = AffiliateOfferLink::withoutGlobalScope('owner_via_affiliate')->sum('conversions');
            $totalRevenue = AffiliateOfferLink::withoutGlobalScope('owner_via_affiliate')->sum('revenue');
            $activeSites = AffiliateSite::query()->withoutOwnerScope()->where('status', AffiliateSite::STATUS_VERIFIED)->count();
            $activeOffers = AffiliateOffer::withoutGlobalScope('owner_via_site')->where('status', AffiliateOffer::STATUS_ACTIVE)->count();
            $pendingApplications = AffiliateOfferApplication::withoutGlobalScope('owner_via_affiliate')->where('status', AffiliateOfferApplication::STATUS_PENDING)->count();

            $conversionRate = $totalClicks > 0
                ? round(($totalConversions / $totalClicks) * 100, 2)
                : 0;

            return [
                'activeSites' => $activeSites,
                'activeOffers' => $activeOffers,
                'pendingApplications' => $pendingApplications,
                'totalClicks' => $totalClicks,
                'totalConversions' => $totalConversions,
                'totalRevenue' => $totalRevenue,
                'conversionRate' => $conversionRate,
                'revenueFormatted' => MoneyFormatter::formatMinor($totalRevenue, 'USD'),
            ];
        });
    }
}
