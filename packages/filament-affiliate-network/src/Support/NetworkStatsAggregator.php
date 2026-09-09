<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Support;

use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
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
            $totalClicks = AffiliateOfferLink::withoutGlobalScope(ScopesByBelongsToOwner::class)->sum('clicks');
            $totalConversions = AffiliateOfferLink::withoutGlobalScope(ScopesByBelongsToOwner::class)->sum('conversions');
            $totalRevenue = AffiliateOfferLink::withoutGlobalScope(ScopesByBelongsToOwner::class)->sum('revenue');
            $activeSites = AffiliateSite::query()->withoutOwnerScope()->where('status', AffiliateSite::STATUS_VERIFIED)->count();
            $activeOffers = AffiliateOffer::withoutGlobalScope(ScopesByBelongsToOwner::class)->where('status', OfferStatus::Published)->count();
            $pendingApplications = AffiliateOfferApplication::withoutGlobalScope(ScopesByBelongsToOwner::class)->where('status', ApplicationStatus::Pending)->count();

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
