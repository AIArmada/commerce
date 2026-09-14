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

        $activeStatus = AffiliateStatus::fromString(Active::class)->getValue();
        $pendingStatus = AffiliateStatus::fromString(Pending::class)->getValue();

        $affiliateRow = $this->affiliateQuery($owner)
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active', [$activeStatus])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending', [$pendingStatus])
            ->first();

        $pendingConversion = PendingConversion::value();
        $paidConversion = PaidConversion::value();
        $approvedConversion = ApprovedConversion::value();

        $conversionRow = $this->conversionQuery($owner)
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN commission_minor ELSE 0 END), 0) as pending_commission', [$pendingConversion])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN commission_minor ELSE 0 END), 0) as paid_commission', [$paidConversion])
            ->selectRaw('COALESCE(SUM(commission_minor), 0) as total_commission')
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as approved', [$approvedConversion, $paidConversion])
            ->first();

        $totalAffiliates = (int) ($affiliateRow->total ?? 0);
        $activeAffiliates = (int) ($affiliateRow->active ?? 0);
        $pendingAffiliates = (int) ($affiliateRow->pending ?? 0);
        $totalConversions = (int) ($conversionRow->total ?? 0);
        $pendingCommission = (int) ($conversionRow->pending_commission ?? 0);
        $paidCommission = (int) ($conversionRow->paid_commission ?? 0);
        $totalCommission = (int) ($conversionRow->total_commission ?? 0);
        $approved = (int) ($conversionRow->approved ?? 0);

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
