<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Enums\RankQualificationReason;
use AIArmada\Affiliates\Events\AffiliateRankChanged;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\Models\AffiliateRankHistory;
use AIArmada\Affiliates\Support\RevenueVolume;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;

final class RankQualificationService
{
    /**
     * Parameter-keyed cache for calculated metrics.
     *
     * @var array<string, array{personal_sales: int, team_sales: int, active_downlines: int, lifetime_value: int}>
     */
    private array $metricsCache = [];

    public function __construct(
        private readonly UplineService $uplineService,
        private readonly Dispatcher $events
    ) {}

    /**
     * Evaluate and determine the highest qualifying rank for an affiliate.
     */
    public function evaluate(Affiliate $affiliate): ?AffiliateRank
    {
        // Order by level DESC to find highest qualifying rank first. Metrics
        // are measured per rank currency so floors never compare across codes.
        return AffiliateRank::query()
            ->orderBy('level', 'desc')
            ->get()
            ->first(function (AffiliateRank $rank) use ($affiliate): bool {
                $metrics = $this->calculateMetrics($affiliate, null, $rank->currencyCode());

                return $rank->meetsQualification(
                    $affiliate,
                    $metrics['personal_sales'],
                    $metrics['team_sales'],
                    $metrics['active_downlines']
                );
            });
    }

    /**
     * Process rank upgrades for all affiliates.
     */
    public function processAllRankUpgrades(): int
    {
        $upgraded = 0;

        Affiliate::query()
            ->with('rank')
            ->chunk(100, function ($affiliates) use (&$upgraded): void {
                foreach ($affiliates as $affiliate) {
                    $newRank = $this->evaluate($affiliate);

                    if ($this->shouldChangeRank($affiliate, $newRank)) {
                        $this->changeRank($affiliate, $newRank, RankQualificationReason::Qualified);
                        $upgraded++;
                    }
                }
            });

        return $upgraded;
    }

    /**
     * Evaluate and promote/demote affiliate to appropriate rank.
     */
    public function processRankChange(Affiliate $affiliate): void
    {
        $newRank = $this->evaluate($affiliate);

        if ($this->shouldChangeRank($affiliate, $newRank)) {
            $this->changeRank($affiliate, $newRank, RankQualificationReason::Qualified);
        }
    }

    /**
     * Process rank changes for a batch of affiliates.
     *
     * @param  iterable<Affiliate>  $affiliates
     */
    public function processBatch(iterable $affiliates): void
    {
        foreach ($affiliates as $affiliate) {
            $this->processRankChange($affiliate);
        }
    }

    /**
     * Manually assign a rank to an affiliate.
     */
    public function assignRank(Affiliate $affiliate, ?AffiliateRank $rank): void
    {
        $this->changeRank($affiliate, $rank, RankQualificationReason::Manual);
    }

    /**
     * Calculate qualification metrics for an affiliate.
     *
     * Results are cached for the request lifetime, keyed by affiliate ID + period.
     *
     * @return array{personal_sales: int, team_sales: int, active_downlines: int, lifetime_value: int}
     */
    public function calculateMetrics(Affiliate $affiliate, ?CarbonImmutable $from = null, ?string $currency = null): array
    {
        $from ??= CarbonImmutable::now()->subDays(30);
        $currency ??= RevenueVolume::referenceFor($affiliate);
        $cacheKey = $this->buildMetricsCacheKey($affiliate, $from, $currency);

        if (isset($this->metricsCache[$cacheKey])) {
            return $this->metricsCache[$cacheKey];
        }

        $personalSales = $this->sumRevenue(
            $affiliate,
            fn ($query) => $query->where('occurred_at', '>=', $from),
            $currency,
        );

        $teamSales = $this->uplineService->getTeamSales($affiliate, $from, null, $currency);

        $activeDownlines = $this->uplineService->getActiveDownlineCount($affiliate);

        $lifetimeValue = $this->sumRevenue($affiliate, null, $currency);

        return $this->metricsCache[$cacheKey] = [
            'personal_sales' => (int) $personalSales,
            'team_sales' => (int) $teamSales,
            'active_downlines' => $activeDownlines,
            'lifetime_value' => (int) $lifetimeValue,
        ];
    }

    /**
     * Clear the metrics cache.
     */
    public function clearCache(): void
    {
        $this->metricsCache = [];
    }

    /**
     * Build cache key for metrics lookup.
     */
    private function buildMetricsCacheKey(Affiliate $affiliate, CarbonImmutable $from, string $currency): string
    {
        return $affiliate->id . ':' . $from->toDateString() . ':' . $currency;
    }

    /**
     * @param  null|callable(mixed): mixed  $scope
     */
    private function sumRevenue(Affiliate $affiliate, ?callable $scope = null, ?string $currency = null): int
    {
        $query = $affiliate->conversions();

        if ($scope !== null) {
            $scope($query);
        }

        $rows = $query
            ->toBase()
            ->selectRaw('commission_currency as currency, COALESCE(SUM(COALESCE(value_minor, 0)), 0) as total')
            ->groupBy('commission_currency')
            ->get();

        $reference = RevenueVolume::referenceFor($affiliate);

        return RevenueVolume::measurableIn(RevenueVolume::foldRows($rows, $reference), $currency ?? $reference);
    }

    private function shouldChangeRank(Affiliate $affiliate, ?AffiliateRank $newRank): bool
    {
        if ($affiliate->rank_id === null && $newRank === null) {
            return false;
        }

        if ($affiliate->rank_id === null && $newRank !== null) {
            return true;
        }

        if ($affiliate->rank_id !== null && $newRank === null) {
            return true;
        }

        return $affiliate->rank_id !== $newRank?->id;
    }

    private function changeRank(Affiliate $affiliate, ?AffiliateRank $newRank, RankQualificationReason $reason): void
    {
        $oldRank = $affiliate->rank;

        AffiliateRankHistory::create([
            'affiliate_id' => $affiliate->id,
            'from_rank_id' => $oldRank?->id,
            'to_rank_id' => $newRank?->id,
            'reason' => $reason,
            'qualified_at' => CarbonImmutable::now(),
        ]);

        $affiliate->update(['rank_id' => $newRank?->id]);

        $this->events->dispatch(new AffiliateRankChanged(
            affiliate: $affiliate,
            fromRank: $oldRank,
            toRank: $newRank,
            reason: $reason
        ));
    }
}
