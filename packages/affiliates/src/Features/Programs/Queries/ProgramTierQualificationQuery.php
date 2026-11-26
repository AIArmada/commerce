<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Queries;

use AIArmada\Affiliates\Features\Attribution\Api\TrackingLinkReader;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfile;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;
use AIArmada\Affiliates\Features\Programs\Api\TierQualificationReader;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Support\RevenueVolume;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

final readonly class ProgramTierQualificationQuery implements TierQualificationReader
{
    public function qualifies(string $affiliateId, string $programId, string $tierId): bool
    {
        $affiliate = app(AffiliateProfileReader::class)->find($affiliateId, spl_object_id((new AffiliateProgramMembership)->getConnection()))
            ?? throw new ModelNotFoundException('Affiliate not found.');
        $program = AffiliateProgram::query()->findOrFail($programId);
        $tier = AffiliateProgramTier::query()->where('program_id', $program->getKey())->orderBy('level')->withoutGlobalScope('program_owner')->whereKey($tierId)->firstOrFail();

        return $this->best($affiliate, $program, collect([$tier])) !== null;
    }

    /** @param Collection<int, AffiliateProgramTier> $tiers */
    public function best(AffiliateProfile $affiliate, AffiliateProgram $program, Collection $tiers): ?AffiliateProgramTier
    {
        if ($tiers->isEmpty()) {
            return null;
        }

        $totals = app(TrackingLinkReader::class)->programVolume(
            $affiliate->reference->id,
            (string) $program->getKey(),
            $affiliate->currency !== null && mb_trim($affiliate->currency) !== '' ? mb_strtoupper(mb_trim($affiliate->currency)) : RevenueVolume::default(),
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
