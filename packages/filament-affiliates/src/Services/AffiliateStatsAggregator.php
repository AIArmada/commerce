<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Services;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\Pending;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class AffiliateStatsAggregator
{
    /**
     * @return array{
     *     total_affiliates:int,
     *     active_affiliates:int,
     *     pending_affiliates:int,
     *     total_conversions:int,
     *     pending_commission_minor:int,
     *     paid_commission_minor:int,
     *     total_commission_minor:int,
     *     conversion_rate:float|null
     * }
     */
    public function overview(): array
    {
        $owner = $this->resolveOwner();
        $affiliateQuery = $this->affiliateQuery($owner);
        $conversionQuery = $this->conversionQuery($owner);

        $totalAffiliates = (clone $affiliateQuery)->count();
        $activeAffiliates = (clone $affiliateQuery)
            ->where('status', AffiliateStatus::fromString(Active::class)->getValue())
            ->count();
        $pendingAffiliates = (clone $affiliateQuery)
            ->where('status', AffiliateStatus::fromString(Pending::class)->getValue())
            ->count();
        $totalConversions = (clone $conversionQuery)->count();
        $pendingCommission = (int) (clone $conversionQuery)
            ->where('status', PendingConversion::value())
            ->sum('commission_minor');
        $paidCommission = (int) (clone $conversionQuery)
            ->where('status', PaidConversion::value())
            ->sum('commission_minor');
        $totalCommission = (int) (clone $conversionQuery)->sum('commission_minor');

        $approved = (clone $conversionQuery)
            ->whereIn('status', [ApprovedConversion::value(), PaidConversion::value()])
            ->count();

        $conversionRate = $totalConversions > 0
            ? ($approved / $totalConversions) * 100
            : null;

        return [
            'total_affiliates' => $totalAffiliates,
            'active_affiliates' => $activeAffiliates,
            'pending_affiliates' => $pendingAffiliates,
            'total_conversions' => $totalConversions,
            'pending_commission_minor' => $pendingCommission,
            'paid_commission_minor' => $paidCommission,
            'total_commission_minor' => $totalCommission,
            'conversion_rate' => $conversionRate,
        ];
    }

    private function affiliateQuery(?Model $owner): Builder
    {
        return Affiliate::query()->forOwner($owner);
    }

    private function conversionQuery(?Model $owner): Builder
    {
        return AffiliateConversion::query()->forOwner($owner);
    }

    private function resolveOwner(): ?Model
    {
        if (! config('affiliates.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }
}
