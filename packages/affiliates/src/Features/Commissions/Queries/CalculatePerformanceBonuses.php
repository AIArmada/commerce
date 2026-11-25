<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Queries;

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Features\Commissions\Api\PerformanceBonusCalculation;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\Support\BonusConfig;
use AIArmada\Affiliates\Support\RevenueVolume;
use AIArmada\CommerceSupport\Support\CurrencyConverter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LogicException;
use stdClass;

final class CalculatePerformanceBonuses implements PerformanceBonusCalculation
{
    /**
     * Calculate one configured performance bonus commission rule type.
     *
     * @return list<array{bonus_type: string, affiliate_id: string, affiliate_name: string, amount_minor: int, reason: string, metrics: array<string, mixed>}>
     */
    public function calculatePerformanceBonuses(
        string $type,
        CarbonImmutable $from,
        CarbonImmutable $to,
        bool $includeGlobal = false,
    ): array {
        $type = CommissionRuleType::from($type);

        if ($from->greaterThan($to)) {
            throw new InvalidArgumentException('Performance bonus start must not exceed the end.');
        }

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
     * @return list<array{bonus_type: string, affiliate_id: string, affiliate_name: string, amount_minor: int, reason: string, metrics: array<string, mixed>}>
     */
    private function calculateTopPerformerBonuses(
        CarbonImmutable $from,
        CarbonImmutable $to,
        bool $includeGlobal,
    ): array {
        $type = CommissionRuleType::TopPerformer;
        $config = BonusConfig::topPerformer();

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
        $config = BonusConfig::recruitment();

        if (! (bool) ($config['enabled'] ?? true)) {
            return [];
        }

        $minimumRecruits = (int) ($config['min_recruits'] ?? 3);

        $recruiters = Affiliate::query()
            ->forOwner(OwnerContext::CURRENT, $includeGlobal)
            ->where('status', AffiliateStatus::normalize(Active::class))
            ->whereCommercialPurpose()
            ->withCount([
                'children' => function (Builder $query) use ($from, $to, $includeGlobal): void {
                    /** @var Builder<Affiliate> $query */
                    $query->forOwner(OwnerContext::CURRENT, $includeGlobal)
                        ->whereBetween('created_at', [$from, $to])
                        ->where('status', AffiliateStatus::normalize(Active::class))
                        ->whereCommercialPurpose();
                },
            ])
            ->whereHas(
                'children',
                function (Builder $query) use ($from, $to, $includeGlobal): void {
                    /** @var Builder<Affiliate> $query */
                    $query->forOwner(OwnerContext::CURRENT, $includeGlobal)
                        ->whereBetween('created_at', [$from, $to])
                        ->where('status', AffiliateStatus::normalize(Active::class))
                        ->whereCommercialPurpose();
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
        $config = BonusConfig::consistency();

        if (! (bool) ($config['enabled'] ?? true)) {
            return [];
        }

        $bonuses = [];
        $minWeeks = $config['min_weeks'] ?? 4;
        $minConversionsPerWeek = $config['min_conversions_per_week'] ?? 1;

        $affiliates = Affiliate::query()
            ->forOwner(OwnerContext::CURRENT, $includeGlobal)
            ->where('status', AffiliateStatus::normalize(Active::class))
            ->whereCommercialPurpose()
            ->get();

        foreach ($affiliates as $affiliate) {
            $weeksWithSales = 0;
            $currentWeek = $from->copy()->startOfWeek();

            while ($currentWeek->lte($to)) {
                $weekEnd = $currentWeek->copy()->endOfWeek();

                $conversionsThisWeek = $affiliate->conversions()
                    ->forOwner(OwnerContext::CURRENT, $includeGlobal)
                    ->whereBetween('occurred_at', [max($currentWeek, $from), min($weekEnd, $to)])
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
                'reason' => "Consistency Bonus - Sales in {$weeksWithSales} qualifying weeks",
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
        $config = BonusConfig::growth();

        if (! (bool) ($config['enabled'] ?? true)) {
            return [];
        }

        $minimumGrowthPercentage = (float) ($config['min_growth_percent'] ?? 25);
        $prevFrom = $from->subMonth()->startOfMonth();
        $prevTo = $from->subMonth()->endOfMonth();
        $bonuses = [];

        $affiliates = Affiliate::query()
            ->forOwner(OwnerContext::CURRENT, $includeGlobal)
            ->where('status', AffiliateStatus::normalize(Active::class))
            ->whereCommercialPurpose()
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

            if ($previousRevenue <= 0 || $previousRevenue < ($config['min_previous_revenue'] ?? 50000)) {
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
                'amount_minor' => (int) ($config['bonus_amount'] ?? 10000),
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
        $conversion = new AffiliateConversion;
        $affiliate = new Affiliate;
        $connection = $conversion->getConnection();

        if ($connection !== $affiliate->getConnection()) {
            throw new LogicException('Performance bonus queries require a shared database connection.');
        }

        $conversionsTable = $conversion->getTable();
        $affiliatesTable = $affiliate->getTable();
        $grammar = $connection->getQueryGrammar();
        $purposeColumn = $grammar->wrap("{$affiliatesTable}.purpose");
        $valueColumn = $grammar->wrap("{$conversionsTable}.value_minor");
        $idColumn = $grammar->wrap("{$conversionsTable}.id");
        $revenueExpression = "COALESCE({$valueColumn}, 0)";

        $query = $connection->table($conversionsTable)
            ->join($affiliatesTable, "{$conversionsTable}.affiliate_id", '=', "{$affiliatesTable}.id")
            ->select([
                "{$affiliatesTable}.id as affiliate_id",
                "{$affiliatesTable}.name as affiliate_name",
                "{$conversionsTable}.commission_currency as currency",
                $connection->raw("SUM({$revenueExpression}) as total_revenue"),
                $connection->raw("COUNT({$idColumn}) as total_conversions"),
            ])
            ->whereBetween("{$conversionsTable}.occurred_at", [$from, $to])
            ->where("{$conversionsTable}.status", ApprovedConversion::value())
            ->where("{$affiliatesTable}.status", AffiliateStatus::normalize(Active::class))
            ->where(static function ($query) use ($affiliatesTable, $purposeColumn): void {
                $query->whereNull("{$affiliatesTable}.purpose")
                    ->orWhereRaw("LOWER(TRIM({$purposeColumn})) = ?", [Affiliate::PURPOSE_GENERAL]);
            })
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
}
