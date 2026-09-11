<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services\Commissions;

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateVolumeTier;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CommissionRuleEngine
{
    /**
     * Parameter-keyed cache for applicable rules.
     *
     * @var array<string, Collection<int, AffiliateCommissionRule>>
     */
    private array $rulesCache = [];

    /**
     * Calculate the commission for a conversion.
     *
     * @param  array<string, mixed>  $context
     */
    public function calculate(
        Affiliate $affiliate,
        int $orderAmountMinor,
        array $context = []
    ): CommissionCalculationResult {
        $context = array_merge($context, [
            'affiliate_id' => $affiliate->id,
            'order_amount_minor' => $orderAmountMinor,
        ]);

        // Get applicable rules ordered by priority
        $rules = $this->getApplicableRules($affiliate, $context);

        // Calculate base commission from highest priority matching rule
        $baseCommission = $this->calculateBaseCommission($rules, $orderAmountMinor, $context);

        // Apply volume tier if applicable
        $volumeBonus = $this->calculateVolumeBonus($affiliate, $orderAmountMinor, $context);

        // Apply promotions
        $promotionBonus = $this->calculatePromotionBonus($affiliate, $baseCommission, $context);

        // Apply caps
        $finalCommission = $this->applyCaps(
            $baseCommission + $volumeBonus + $promotionBonus,
            $affiliate,
            $context
        );

        return new CommissionCalculationResult(
            baseCommissionMinor: $baseCommission,
            volumeBonusMinor: $volumeBonus,
            promotionBonusMinor: $promotionBonus,
            finalCommissionMinor: $finalCommission,
            appliedRules: $rules->pluck('id')->all(),
            metadata: [
                'order_amount_minor' => $orderAmountMinor,
                'context' => $context,
            ]
        );
    }

    /**
     * Get all applicable rules for the context.
     *
     * Results are cached for the request lifetime, keyed by affiliate + context.
     *
     * @return Collection<int, AffiliateCommissionRule>
     */
    public function getApplicableRules(Affiliate $affiliate, array $context): Collection
    {
        $cacheKey = $this->buildRulesCacheKey($affiliate, $context);

        if (isset($this->rulesCache[$cacheKey])) {
            return $this->rulesCache[$cacheKey];
        }

        $programId = $context['program_id'] ?? null;

        return $this->rulesCache[$cacheKey] = AffiliateCommissionRule::query()
            ->active()
            ->ordered()
            ->when(
                $programId,
                fn (Builder $query) => $query->where(function (Builder $inner) use ($programId): void {
                    $inner->whereNull('program_id')->orWhere('program_id', $programId);
                }),
                fn (Builder $query) => $query->whereNull('program_id')
            )
            ->get()
            ->filter(fn (AffiliateCommissionRule $rule): bool => ! $rule->rule_type->isPerformanceBonus() && $rule->matches($context));
    }

    /**
     * Clear the rules cache.
     */
    public function clearCache(): void
    {
        $this->rulesCache = [];
    }

    /**
     * Calculate one configured performance bonus commission rule type.
     *
     * @return list<array{bonus_type: string, affiliate_id: string, affiliate_name: string, amount_minor: int, reason: string, metrics: array<string, mixed>}>
     */
    public function calculatePerformanceBonuses(
        CommissionRuleType $type,
        CarbonImmutable $from,
        CarbonImmutable $to,
        bool $includeGlobal = false,
    ): array {
        if (! $type->isPerformanceBonus()) {
            throw new InvalidArgumentException(sprintf(
                'Commission rule type [%s] is not a performance bonus.',
                $type->value,
            ));
        }

        return match ($type) {
            CommissionRuleType::TopPerformer => $this->calculateTopPerformerBonuses($from, $to, $includeGlobal),
            CommissionRuleType::Recruitment => $this->calculateRecruitmentBonuses($from, $to, $includeGlobal),
            CommissionRuleType::Consistency => $this->calculateConsistencyBonuses($from, $to, $includeGlobal),
            CommissionRuleType::Growth => $this->calculateGrowthBonuses($from, $to, $includeGlobal),
            default => [],
        };
    }

    /**
     * Build cache key for rules lookup.
     *
     * @param  array<string, mixed>  $context
     */
    private function buildRulesCacheKey(Affiliate $affiliate, array $context): string
    {
        return md5($affiliate->id . ':' . serialize($context));
    }

    /**
     * @param  Collection<int, AffiliateCommissionRule>  $rules
     */
    private function calculateBaseCommission(Collection $rules, int $orderAmountMinor, array $context): int
    {
        // Use the highest priority matching rule
        $rule = $rules->first();

        if (! $rule) {
            // Fall back to default config rate
            $defaultRateBasisPoints = (int) config('affiliates.commissions.default_rate', 1000);

            return (int) round($orderAmountMinor * $defaultRateBasisPoints / 10000);
        }

        return $rule->calculateCommission($orderAmountMinor);
    }

    private function calculateVolumeBonus(Affiliate $affiliate, int $orderAmountMinor, array $context): int
    {
        $programId = $context['program_id'] ?? null;

        // Get affiliate's volume for the period
        $volumeQuery = $affiliate->conversions();

        if ($programId) {
            $volumeQuery->where(function (Builder $query) use ($programId): void {
                $query
                    ->whereHas('affiliateLink', fn (Builder $linkQuery) => $linkQuery->where('program_id', $programId))
                    ->orWhereHas('attribution.affiliateLink', fn (Builder $linkQuery) => $linkQuery->where('program_id', $programId));
            });
        }

        $periodVolume = (int) $volumeQuery
            ->where('occurred_at', '>=', CarbonImmutable::now()->startOfMonth())
            ->sum(DB::raw('COALESCE(value_minor, 0)'));

        // Find applicable volume tier
        $tier = AffiliateVolumeTier::query()
            ->when(
                $programId,
                fn (Builder $query) => $query->where(function (Builder $inner) use ($programId): void {
                    $inner->whereNull('program_id')->orWhere('program_id', $programId);
                }),
                fn (Builder $query) => $query->whereNull('program_id')
            )
            ->where('min_volume_minor', '<=', $periodVolume)
            ->where(function ($q) use ($periodVolume): void {
                $q->whereNull('max_volume_minor')
                    ->orWhere('max_volume_minor', '>=', $periodVolume);
            })
            ->orderBy('min_volume_minor', 'desc')
            ->first();

        if (! $tier) {
            return 0;
        }

        // Calculate bonus based on volume tier rate
        $tierCommission = (int) round($orderAmountMinor * $tier->commission_rate_basis_points / 10000);
        $defaultRateBasisPoints = (int) config('affiliates.commissions.default_rate', 1000);
        $defaultCommission = (int) round($orderAmountMinor * $defaultRateBasisPoints / 10000);

        // Return the difference as bonus
        return max(0, $tierCommission - $defaultCommission);
    }

    private function calculatePromotionBonus(Affiliate $affiliate, int $baseCommission, array $context): int
    {
        $programId = $context['program_id'] ?? null;

        $promotions = AffiliateCommissionPromotion::query()
            ->active()
            ->when(
                $programId,
                fn (Builder $query) => $query->where(function (Builder $inner) use ($programId): void {
                    $inner->whereNull('program_id')->orWhere('program_id', $programId);
                }),
                fn (Builder $query) => $query->whereNull('program_id')
            )
            ->get()
            ->filter(fn (AffiliateCommissionPromotion $promo) => $promo->appliesToAffiliate($affiliate));

        $totalBonus = 0;

        foreach ($promotions as $promotion) {
            $bonus = $promotion->calculateBonus($baseCommission);
            $totalBonus += $bonus;
            $promotion->incrementUsage();
        }

        return $totalBonus;
    }

    /**
     * @return list<array{bonus_type: string, affiliate_id: string, affiliate_name: string, amount_minor: int, reason: string, metrics: array<string, mixed>}>
     */
    private function calculateTopPerformerBonuses(
        CarbonImmutable $from,
        CarbonImmutable $to,
        bool $includeGlobal,
    ): array {
        $type = CommissionRuleType::TopPerformer;
        $config = config('affiliates.bonuses.top_performer', []);

        if (! (bool) ($config['enabled'] ?? true)) {
            return [];
        }

        $bonuses = [];

        foreach ($this->performanceLeaderboard($from, $to, 3, $includeGlobal) as $entry) {
            if ($entry['total_revenue'] < ($config['min_revenue'] ?? 100000)) {
                continue;
            }

            $position = $entry['rank'];
            $bonusAmount = $config['positions'][$position] ?? 0;

            if ($bonusAmount <= 0) {
                continue;
            }

            $bonuses[] = [
                'bonus_type' => $type->value,
                'affiliate_id' => $entry['affiliate_id'],
                'affiliate_name' => $entry['affiliate_name'],
                'amount_minor' => (int) $bonusAmount,
                'reason' => "Top Performer Bonus - #{$position} for " . $from->format('F Y'),
                'metrics' => [
                    'position' => $position,
                    'total_revenue' => $entry['total_revenue'],
                    'total_conversions' => $entry['total_conversions'],
                    'period' => $from->format('Y-m'),
                ],
            ];
        }

        return $bonuses;
    }

    /**
     * @return list<array{bonus_type: string, affiliate_id: string, affiliate_name: string, amount_minor: int, reason: string, metrics: array<string, mixed>}>
     */
    private function calculateRecruitmentBonuses(
        CarbonImmutable $from,
        CarbonImmutable $to,
        bool $includeGlobal,
    ): array {
        $type = CommissionRuleType::Recruitment;
        $config = config('affiliates.bonuses.recruitment', []);

        if (! (bool) ($config['enabled'] ?? true)) {
            return [];
        }

        $minimumRecruits = (int) ($config['min_recruits'] ?? 3);

        $recruiters = Affiliate::query()
            ->forOwner(OwnerContext::CURRENT, $includeGlobal)
            ->where('status', AffiliateStatus::normalize(Active::class))
            ->withCount([
                'children' => function (Builder $query) use ($from, $to, $includeGlobal): void {
                    /** @var Builder<Affiliate> $query */
                    $query->forOwner(OwnerContext::CURRENT, $includeGlobal)
                        ->whereBetween('created_at', [$from, $to])
                        ->where('status', AffiliateStatus::normalize(Active::class));
                },
            ])
            ->whereHas(
                'children',
                function (Builder $query) use ($from, $to, $includeGlobal): void {
                    /** @var Builder<Affiliate> $query */
                    $query->forOwner(OwnerContext::CURRENT, $includeGlobal)
                        ->whereBetween('created_at', [$from, $to])
                        ->where('status', AffiliateStatus::normalize(Active::class));
                },
                '>=',
                $minimumRecruits,
            )
            ->get();

        $bonuses = [];

        foreach ($recruiters as $recruiter) {
            $recruitCount = (int) $recruiter->children_count;
            $bonusAmount = min(
                $recruitCount * ($config['bonus_per_recruit'] ?? 2500),
                $config['max_bonus'] ?? 25000,
            );

            $bonuses[] = [
                'bonus_type' => $type->value,
                'affiliate_id' => $recruiter->id,
                'affiliate_name' => $recruiter->name,
                'amount_minor' => (int) $bonusAmount,
                'reason' => "Recruitment Bonus - {$recruitCount} new active recruits in " . $from->format('F Y'),
                'metrics' => [
                    'recruit_count' => $recruitCount,
                    'period' => $from->format('Y-m'),
                ],
            ];
        }

        return $bonuses;
    }

    /**
     * @return list<array{bonus_type: string, affiliate_id: string, affiliate_name: string, amount_minor: int, reason: string, metrics: array<string, mixed>}>
     */
    private function calculateConsistencyBonuses(
        CarbonImmutable $from,
        CarbonImmutable $to,
        bool $includeGlobal,
    ): array {
        $type = CommissionRuleType::Consistency;
        $config = config('affiliates.bonuses.consistency', []);

        if (! (bool) ($config['enabled'] ?? true)) {
            return [];
        }

        $bonuses = [];
        $minWeeks = $config['min_weeks'] ?? 4;
        $minConversionsPerWeek = $config['min_conversions_per_week'] ?? 1;

        $affiliates = Affiliate::query()
            ->forOwner(OwnerContext::CURRENT, $includeGlobal)
            ->where('status', AffiliateStatus::normalize(Active::class))
            ->get();

        foreach ($affiliates as $affiliate) {
            $weeksWithSales = 0;
            $currentWeek = $from->copy()->startOfWeek();

            while ($currentWeek->lte($to)) {
                $weekEnd = $currentWeek->copy()->endOfWeek();

                $conversionsThisWeek = $affiliate->conversions()
                    ->forOwner(OwnerContext::CURRENT, $includeGlobal)
                    ->whereBetween('occurred_at', [$currentWeek, $weekEnd])
                    ->where('status', ApprovedConversion::value())
                    ->count();

                if ($conversionsThisWeek >= $minConversionsPerWeek) {
                    $weeksWithSales++;
                }

                $currentWeek = $currentWeek->addWeek();
            }

            if ($weeksWithSales < $minWeeks) {
                continue;
            }

            $bonuses[] = [
                'bonus_type' => $type->value,
                'affiliate_id' => $affiliate->id,
                'affiliate_name' => $affiliate->name,
                'amount_minor' => (int) ($config['bonus_amount'] ?? 5000),
                'reason' => "Consistency Bonus - Sales in {$weeksWithSales} consecutive weeks",
                'metrics' => [
                    'weeks_with_sales' => $weeksWithSales,
                    'period' => $from->format('Y-m'),
                ],
            ];
        }

        return $bonuses;
    }

    /**
     * @return list<array{bonus_type: string, affiliate_id: string, affiliate_name: string, amount_minor: int, reason: string, metrics: array<string, mixed>}>
     */
    private function calculateGrowthBonuses(
        CarbonImmutable $from,
        CarbonImmutable $to,
        bool $includeGlobal,
    ): array {
        $type = CommissionRuleType::Growth;
        $config = config('affiliates.bonuses.growth', []);

        if (! (bool) ($config['enabled'] ?? true)) {
            return [];
        }

        $minimumGrowthPercentage = (float) ($config['min_growth_percent'] ?? 50);
        $prevFrom = $from->subMonth()->startOfMonth();
        $prevTo = $from->subMonth()->endOfMonth();
        $bonuses = [];

        $affiliates = Affiliate::query()
            ->forOwner(OwnerContext::CURRENT, $includeGlobal)
            ->where('status', AffiliateStatus::normalize(Active::class))
            ->get();

        foreach ($affiliates as $affiliate) {
            $currentRevenue = $affiliate->conversions()
                ->forOwner(OwnerContext::CURRENT, $includeGlobal)
                ->whereBetween('occurred_at', [$from, $to])
                ->where('status', ApprovedConversion::value())
                ->sum(DB::raw('COALESCE(value_minor, 0)'));

            $previousRevenue = $affiliate->conversions()
                ->forOwner(OwnerContext::CURRENT, $includeGlobal)
                ->whereBetween('occurred_at', [$prevFrom, $prevTo])
                ->where('status', ApprovedConversion::value())
                ->sum(DB::raw('COALESCE(value_minor, 0)'));

            if ($previousRevenue < ($config['min_previous_revenue'] ?? 50000)) {
                continue;
            }

            $growthPercentage = (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;

            if ($growthPercentage < $minimumGrowthPercentage) {
                continue;
            }

            $bonuses[] = [
                'bonus_type' => $type->value,
                'affiliate_id' => $affiliate->id,
                'affiliate_name' => $affiliate->name,
                'amount_minor' => (int) ($config['bonus_amount'] ?? 7500),
                'reason' => 'Growth Bonus - ' . round($growthPercentage, 1) . '% growth vs previous month',
                'metrics' => [
                    'current_revenue' => $currentRevenue,
                    'previous_revenue' => $previousRevenue,
                    'growth_percentage' => round($growthPercentage, 2),
                    'period' => $from->format('Y-m'),
                ],
            ];
        }

        return $bonuses;
    }

    /**
     * @return list<array{rank: int, affiliate_id: string, affiliate_name: string, total_revenue: int, total_conversions: int}>
     */
    private function performanceLeaderboard(
        CarbonImmutable $from,
        CarbonImmutable $to,
        int $limit,
        bool $includeGlobal,
    ): array {
        $conversionsTable = (new AffiliateConversion)->getTable();
        $affiliatesTable = (new Affiliate)->getTable();
        $revenueExpression = "COALESCE({$conversionsTable}.value_minor, 0)";

        $query = DB::table($conversionsTable)
            ->join($affiliatesTable, "{$conversionsTable}.affiliate_id", '=', "{$affiliatesTable}.id")
            ->select([
                "{$affiliatesTable}.id as affiliate_id",
                "{$affiliatesTable}.name as affiliate_name",
                DB::raw("SUM({$revenueExpression}) as total_revenue"),
                DB::raw("COUNT({$conversionsTable}.id) as total_conversions"),
            ])
            ->whereBetween("{$conversionsTable}.occurred_at", [$from, $to])
            ->where("{$conversionsTable}.status", ApprovedConversion::value())
            ->where("{$affiliatesTable}.status", AffiliateStatus::normalize(Active::class))
            ->groupBy("{$affiliatesTable}.id", "{$affiliatesTable}.name")
            ->orderByDesc('total_revenue')
            ->limit($limit);

        if ((bool) config('affiliates.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                'Performance bonus queries require an owner context or explicit global context.',
            );

            OwnerQuery::applyToQueryBuilder(
                $query,
                $owner,
                $includeGlobal,
                "{$conversionsTable}.owner_type",
                "{$conversionsTable}.owner_id",
            );
            OwnerQuery::applyToQueryBuilder(
                $query,
                $owner,
                $includeGlobal,
                "{$affiliatesTable}.owner_type",
                "{$affiliatesTable}.owner_id",
            );
        }

        return $query->get()
            ->map(fn (object $row, int $index): array => [
                'rank' => $index + 1,
                'affiliate_id' => (string) $row->affiliate_id,
                'affiliate_name' => (string) $row->affiliate_name,
                'total_revenue' => (int) $row->total_revenue,
                'total_conversions' => (int) $row->total_conversions,
            ])
            ->all();
    }

    private function applyCaps(int $commission, Affiliate $affiliate, array $context): int
    {
        $minCommission = config('affiliates.commissions.minimum_minor', 0);
        $maxCommission = config('affiliates.commissions.maximum_minor');

        if ($commission < $minCommission) {
            return $minCommission;
        }

        if ($maxCommission !== null && $commission > $maxCommission) {
            return $maxCommission;
        }

        return $commission;
    }
}
