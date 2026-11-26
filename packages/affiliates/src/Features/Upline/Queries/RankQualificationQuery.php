<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Queries;

use AIArmada\Affiliates\Features\Conversions\Api\ConversionStatisticsReader;
use AIArmada\Affiliates\Features\Upline\Api\UplineApi;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateRank;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Support\RevenueVolume;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class RankQualificationQuery
{
    public function __construct(private UplineApi $upline, private ConversionStatisticsReader $conversions) {}

    public function evaluate(Affiliate $affiliate, bool $lockRanks = false): ?AffiliateRank
    {
        if (! $affiliate->isCommercialPurpose()) {
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

            if ($rank->meetsQualification($affiliate, $values['personal_sales'], $values['team_sales'], $values['active_downlines'])) {
                return $rank;
            }
        }

        return null;
    }

    /** @return Builder<AffiliateRank> */
    public function ranksFor(Affiliate $affiliate): Builder
    {
        $query = AffiliateRank::query()->forOwner();

        if (config('affiliates.owner.enabled', false)) {
            $query->where('owner_type', $affiliate->owner_type)->where('owner_id', $affiliate->owner_id);
        }

        return $query;
    }

    /** @return array{personal_sales: int, team_sales: int, active_downlines: int, lifetime_value: int} */
    public function calculateMetrics(Affiliate $affiliate, ?CarbonImmutable $from = null, ?string $currency = null): array
    {
        $from ??= CarbonImmutable::now()->subDays(30);
        $reference = RevenueVolume::referenceFor($affiliate);
        $currency ??= $reference;

        $revenue = $this->conversions->revenueWindow((string) $affiliate->getKey(), $reference, spl_object_id($affiliate->getConnection()), $from);

        return [
            'personal_sales' => RevenueVolume::measurableIn($revenue->personalByCurrency, $currency),
            'team_sales' => $this->upline->teamSales((string) $affiliate->getKey(), $from, null, $currency),
            'active_downlines' => $this->upline->activeDownlineCount((string) $affiliate->getKey()),
            'lifetime_value' => RevenueVolume::measurableIn($revenue->lifetimeByCurrency, $currency),
        ];
    }
}
