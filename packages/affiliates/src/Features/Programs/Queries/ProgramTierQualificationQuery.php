<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Queries;

use AIArmada\Affiliates\Features\Programs\Api\TierQualificationReader;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramTier;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\Support\RevenueVolume;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class ProgramTierQualificationQuery implements TierQualificationReader
{
    public function qualifies(string $affiliateId, string $programId, string $tierId): bool
    {
        $affiliate = Affiliate::query()->findOrFail($affiliateId);
        $program = AffiliateProgram::query()->findOrFail($programId);
        $tier = AffiliateProgramTier::query()->where('program_id', $program->getKey())->orderBy('level')->withoutGlobalScope('program_owner')->whereKey($tierId)->firstOrFail();

        return $this->best($affiliate, $program, collect([$tier])) !== null;
    }

    /** @param Collection<int, AffiliateProgramTier> $tiers */
    public function best(Affiliate $affiliate, AffiliateProgram $program, Collection $tiers): ?AffiliateProgramTier
    {
        if ($tiers->isEmpty()) {
            return null;
        }

        $rows = $affiliate->conversions()
            ->whereIn('status', [ApprovedConversion::value(), PaidConversion::value()])
            ->where(function (Builder $query) use ($program): void {
                $query->whereHas('affiliateLink', fn (Builder $linkQuery) => $linkQuery->where('program_id', $program->id))
                    ->orWhereHas('attribution.affiliateLink', fn (Builder $linkQuery) => $linkQuery->where('program_id', $program->id));
            })
            ->toBase()
            ->selectRaw('commission_currency as currency, COUNT(*) as conversion_count, COALESCE(SUM(COALESCE(value_minor, 0)), 0) as total')
            ->groupBy('commission_currency')
            ->get();

        $count = (int) $rows->sum('conversion_count');
        $volume = RevenueVolume::foldRows($rows, RevenueVolume::referenceFor($affiliate));
        $revenue = [];

        foreach ($tiers->sortByDesc('level') as $tier) {
            if ($count < $tier->min_conversions) {
                continue;
            }

            $currency = $tier->revenueCurrency();
            $revenue[$currency] ??= RevenueVolume::measurableIn($volume, $currency);

            if ($revenue[$currency] >= $tier->min_revenue) {
                return $tier;
            }
        }

        return null;
    }
}
