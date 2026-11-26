<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Queries;

use AIArmada\Affiliates\Features\Attribution\Api\TrackingLinkReader;
use AIArmada\Affiliates\Features\Programs\Api\TierQualificationReader;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Support\RevenueVolume;
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

        $totals = app(TrackingLinkReader::class)->programVolume(
            (string) $affiliate->getKey(),
            (string) $program->getKey(),
            RevenueVolume::referenceFor($affiliate),
            approvedOnly: true,
        );

        $count = $totals->count;
        $volume = $totals->byCurrency;
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
