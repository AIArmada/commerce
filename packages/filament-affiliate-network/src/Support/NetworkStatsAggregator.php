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
     * Network-wide totals for the admin dashboard.
     *
     * Multi-currency limitation: revenue is summed in minor units across all
     * offers and formatted as USD. Per-offer currency formatting is used
     * wherever a single offer is displayed (see TopOffersWidget); treat the
     * network revenue total as indicative when offers span currencies.
     *
     * @return array{activeSites: int, activeOffers: int, pendingApplications: int, totalClicks: int, totalConversions: int, totalRevenue: int, conversionRate: float, revenueFormatted: string}
     */
    public static function aggregate(): array
    {
        return OwnerContext::withOwner(null, function (): array {
            // sum() returns int|string depending on the driver — cast to the
            // documented int shape (strict_types would TypeError otherwise).
            $totalClicks = (int) AffiliateOfferLink::withoutGlobalScope(ScopesByBelongsToOwner::class)->sum('clicks');
            $totalConversions = (int) AffiliateOfferLink::withoutGlobalScope(ScopesByBelongsToOwner::class)->sum('conversions');
            $totalRevenue = (int) AffiliateOfferLink::withoutGlobalScope(ScopesByBelongsToOwner::class)->sum('revenue');
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
