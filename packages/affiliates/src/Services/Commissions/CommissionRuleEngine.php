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
use AIArmada\Affiliates\Support\RevenueVolume;
use AIArmada\CommerceSupport\Support\CurrencyConverter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use stdClass;

final class CommissionRuleEngine
{
    /**
     * Parameter-keyed cache for applicable rules.
     *
     * @var array<string, Collection<int, AffiliateCommissionRule>>
     */
    private array $rulesCache = [];

    /**
     * Program-keyed cache for volume tiers.
     *
     * @var array<string, Collection<int, AffiliateVolumeTier>>
     */
    private array $volumeTiersCache = [];

    /**
     * Program-keyed cache for active promotions.
     *
     * @var array<string, Collection<int, AffiliateCommissionPromotion>>
     */
    private array $promotionsCache = [];

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
        $this->volumeTiersCache = [];
        $this->promotionsCache = [];
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
     * @return Collection<int, AffiliateVolumeTier>
     */
    private function volumeTiersForProgram(mixed $programId): Collection
    {
        $cacheKey = is_scalar($programId) ? (string) $programId : 'global';

        return $this->volumeTiersCache[$cacheKey] ??= AffiliateVolumeTier::query()
            ->when(
                $programId,
                fn (Builder $query) => $query->where(function (Builder $inner) use ($programId): void {
                    $inner->whereNull('program_id')->orWhere('program_id', $programId);
                }),
                fn (Builder $query) => $query->whereNull('program_id')
            )
            ->orderBy('min_volume_minor')
            ->get();
    }

    /**
     * @return Collection<int, AffiliateCommissionPromotion>
     */
    private function promotionsForProgram(mixed $programId): Collection
    {
        $cacheKey = is_scalar($programId) ? (string) $programId : 'global';

        return $this->promotionsCache[$cacheKey] ??= AffiliateCommissionPromotion::query()
            ->active()
            ->when(
                $programId,
                fn (Builder $query) => $query->where(function (Builder $inner) use ($programId): void {
                    $inner->whereNull('program_id')->orWhere('program_id', $programId);
                }),
                fn (Builder $query) => $query->whereNull('program_id')
            )
            ->get();
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

        $volumeRows = $volumeQuery
            ->where('occurred_at', '>=', CarbonImmutable::now()->startOfMonth())
            ->toBase()
            ->selectRaw('commission_currency as currency, COALESCE(SUM(COALESCE(value_minor, 0)), 0) as total')
            ->groupBy('commission_currency')
            ->get();

        $reference = RevenueVolume::referenceFor($affiliate);
        $folded = RevenueVolume::foldRows($volumeRows, $reference);
        $volumeIn = [];

        $volumeFor = function (string $currency) use ($folded, &$volumeIn): int {
            return $volumeIn[$currency] ??= RevenueVolume::measurableIn($folded, $currency);
        };

        // Tiers qualify in their own currency; mixed-currency tiers order by
        // converted floor with unconvertible tiers sinking last.
        $tier = $this->volumeTiersForProgram($programId)
            ->filter(fn (AffiliateVolumeTier $candidate): bool => $candidate->containsVolume($volumeFor($candidate->currencyCode())))
            ->sort(function (AffiliateVolumeTier $left, AffiliateVolumeTier $right) use ($reference): int {
                $converter = app(CurrencyConverter::class);
                $leftMin = $converter->convertMinor($left->min_volume_minor, $left->currencyCode(), $reference);
                $rightMin = $converter->convertMinor($right->min_volume_minor, $right->currencyCode(), $reference);

                if ($leftMin === null && $rightMin === null) {
                    return 0;
                }

                if ($leftMin === null) {
                    return 1;
                }

                if ($rightMin === null) {
                    return -1;
                }

                return $rightMin <=> $leftMin;
            })
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

        $promotions = $this->promotionsForProgram($programId)
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
        $thresholdCurrency = mb_strtoupper((string) ($config['min_revenue_currency'] ?? config('affiliates.currency.default', 'MYR')));

        foreach ($this->performanceLeaderboard($from, $to, 3, $includeGlobal) as $entry) {
            $revenue = app(CurrencyConverter::class)->totalMinor($entry['revenue_by_currency'], $thresholdCurrency, $to);

            if ($revenue === null || $revenue < ($config['min_revenue'] ?? 100000)) {
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

        $floorCurrency = mb_strtoupper((string) ($config['min_previous_revenue_currency'] ?? config('affiliates.currency.default', 'MYR')));

        foreach ($affiliates as $affiliate) {
            $reference = RevenueVolume::referenceFor($affiliate);

            $currentRevenue = RevenueVolume::measurableIn(
                RevenueVolume::foldRows($this->revenueByCurrency($affiliate, $from, $to, $includeGlobal), $reference),
                $floorCurrency,
                $to,
            );

            $previousRevenue = RevenueVolume::measurableIn(
                RevenueVolume::foldRows($this->revenueByCurrency($affiliate, $prevFrom, $prevTo, $includeGlobal), $reference),
                $floorCurrency,
                $prevTo,
            );

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
     * @return Collection<int, stdClass>
     */
    private function revenueByCurrency(Affiliate $affiliate, CarbonImmutable $from, CarbonImmutable $to, bool $includeGlobal): Collection
    {
        return $affiliate->conversions()
            ->forOwner(OwnerContext::CURRENT, $includeGlobal)
            ->whereBetween('occurred_at', [$from, $to])
            ->where('status', ApprovedConversion::value())
            ->toBase()
            ->selectRaw('commission_currency as currency, COALESCE(SUM(COALESCE(value_minor, 0)), 0) as total')
            ->groupBy('commission_currency')
            ->get();
    }

    /**
     * Homogeneous networks rank raw sums; mixed networks convert to the
     * package default, and entries without a rate sink below ranked ones.
     *
     * @return list<array{rank: int, affiliate_id: string, affiliate_name: string, total_revenue: int|null, total_conversions: int, revenue_currency: string, revenue_converted: bool, revenue_by_currency: array<string, int>}>
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
                "{$conversionsTable}.commission_currency as currency",
                DB::raw("SUM({$revenueExpression}) as total_revenue"),
                DB::raw("COUNT({$conversionsTable}.id) as total_conversions"),
            ])
            ->whereBetween("{$conversionsTable}.occurred_at", [$from, $to])
            ->where("{$conversionsTable}.status", ApprovedConversion::value())
            ->where("{$affiliatesTable}.status", AffiliateStatus::normalize(Active::class))
            ->groupBy("{$affiliatesTable}.id", "{$affiliatesTable}.name", "{$conversionsTable}.commission_currency");

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

        $default = RevenueVolume::default();
        $entries = [];

        foreach ($query->get() as $row) {
            $id = (string) $row->affiliate_id;

            $entries[$id] ??= [
                'affiliate_id' => $id,
                'affiliate_name' => (string) $row->affiliate_name,
                'total_conversions' => 0,
                'by_currency' => [],
            ];

            $entries[$id]['total_conversions'] += (int) $row->total_conversions;

            $currency = is_string($row->currency) && mb_trim($row->currency) !== ''
                ? mb_strtoupper(mb_trim($row->currency))
                : $default;

            $entries[$id]['by_currency'][$currency] = ($entries[$id]['by_currency'][$currency] ?? 0) + (int) $row->total_revenue;
        }

        $networkCurrencies = [];

        foreach ($entries as $entry) {
            foreach (array_keys($entry['by_currency']) as $currency) {
                $networkCurrencies[$currency] = true;
            }
        }

        // Homogeneous networks rank raw sums untouched; mixed networks convert
        // to the default, and entries without a rate sink below ranked ones.
        $homogeneous = count($networkCurrencies) === 1;

        foreach ($entries as $id => $entry) {
            if ($homogeneous) {
                $only = (string) array_key_first($entry['by_currency']);

                $entries[$id]['total_revenue'] = $entry['by_currency'][$only] ?? 0;
                $entries[$id]['revenue_currency'] = $only;
                $entries[$id]['revenue_converted'] = false;

                continue;
            }

            $converted = app(CurrencyConverter::class)->totalMinor($entry['by_currency'], $default, $to);

            $entries[$id]['total_revenue'] = $converted;
            $entries[$id]['revenue_currency'] = $default;
            $entries[$id]['revenue_converted'] = $converted !== null;
        }

        usort($entries, function (array $left, array $right): int {
            if ($left['total_revenue'] === null && $right['total_revenue'] === null) {
                return $left['affiliate_id'] <=> $right['affiliate_id'];
            }

            if ($left['total_revenue'] === null) {
                return 1;
            }

            if ($right['total_revenue'] === null) {
                return -1;
            }

            return [$right['total_revenue'], $left['affiliate_id']] <=> [$left['total_revenue'], $right['affiliate_id']];
        });

        return collect(array_slice($entries, 0, $limit))
            ->map(fn (array $entry, int $index): array => [
                'rank' => $index + 1,
                'affiliate_id' => $entry['affiliate_id'],
                'affiliate_name' => $entry['affiliate_name'],
                'total_revenue' => $entry['total_revenue'],
                'total_conversions' => $entry['total_conversions'],
                'revenue_currency' => $entry['revenue_currency'],
                'revenue_converted' => $entry['revenue_converted'],
                'revenue_by_currency' => $entry['by_currency'],
            ])
            ->all();
    }

    private function applyCaps(int $commission, Affiliate $affiliate, array $context): int
    {
        return CommissionCaps::clamp($commission);
    }
}
