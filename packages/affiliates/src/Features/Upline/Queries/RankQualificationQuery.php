<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Queries;

use AIArmada\Affiliates\Features\Conversions\Api\ConversionStatisticsReader;
use AIArmada\Affiliates\Features\Upline\Api\UplineApi;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateRank;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfile;
use AIArmada\Affiliates\Support\RevenueVolume;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class RankQualificationQuery
{
    public function __construct(private UplineApi $upline, private ConversionStatisticsReader $conversions) {}

    public function evaluate(AffiliateProfile $affiliate, bool $lockRanks = false): ?AffiliateRank
    {
        if (! $affiliate->commercial) {
            return null;
        }

        $from = CarbonImmutable::now()->subDays(30);
        $metrics = [];
        $ranks = $this->ranksFor($affiliate)->orderByDesc('level')->orderBy('id');
        if ($lockRanks) {
            $ranks->sharedLock();
        }

        foreach ($ranks->get() as $rank) {
            $currency = $rank->currencyCode();
            $values = $metrics[$currency] ??= $this->calculateMetrics($affiliate, $from, $currency);

            if ($rank->meetsQualification($values['personal_sales'], $values['team_sales'], $values['active_downlines'])) {
                return $rank;
            }
        }

        return null;
    }

    /** @return Builder<AffiliateRank> */
    public function ranksFor(AffiliateProfile $affiliate): Builder
    {
        $query = AffiliateRank::query()->forOwner();

        if (config('affiliates.owner.enabled', false)) {
            $query->where('owner_type', $affiliate->reference->ownerType)->where('owner_id', $affiliate->reference->ownerId);
        }

        return $query;
    }

    /** @return array{personal_sales: int, team_sales: int, active_downlines: int, lifetime_value: int} */
    public function calculateMetrics(AffiliateProfile $affiliate, ?CarbonImmutable $from = null, ?string $currency = null): array
    {
        $from ??= CarbonImmutable::now()->subDays(30);
        $reference = $affiliate->currency !== null && mb_trim($affiliate->currency) !== '' ? mb_strtoupper(mb_trim($affiliate->currency)) : RevenueVolume::default();
        $currency ??= $reference;

        $revenue = $this->conversions->revenueWindow($affiliate->reference->id, $reference, spl_object_id((new AffiliateRank)->getConnection()), $from);

        return [
            'personal_sales' => RevenueVolume::measurableIn($revenue->personalByCurrency, $currency),
            'team_sales' => $this->upline->teamSales($affiliate->reference->id, $from, null, $currency),
            'active_downlines' => $this->upline->activeDownlineCount($affiliate->reference->id),
            'lifetime_value' => RevenueVolume::measurableIn($revenue->lifetimeByCurrency, $currency),
        ];
    }
}
