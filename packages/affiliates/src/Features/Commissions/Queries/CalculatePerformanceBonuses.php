<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Queries;

use AIArmada\Affiliates\Contracts\MerchantIdentity;
use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Features\Commissions\Api\PerformanceBonusCalculation;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionReportingReader;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionStatisticsReader;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateBonusCandidate;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateBonusReader;
use AIArmada\Affiliates\Support\BonusConfig;
use AIArmada\Affiliates\Support\RevenueVolume;
use AIArmada\CommerceSupport\Support\CurrencyConverter;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class CalculatePerformanceBonuses implements PerformanceBonusCalculation
{
    public function __construct(private ConversionStatisticsReader $statistics, private ConversionReportingReader $reporting) {}

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

        $recruiters = app(AffiliateBonusReader::class)->recruiters($from, $to, $minimumRecruits, $includeGlobal);

        $bonuses = [];

        foreach ($recruiters as $recruiter) {
            $recruitCount = (int) $recruiter->recruitCount;
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

        $affiliates = app(AffiliateBonusReader::class)->candidates($includeGlobal);

        foreach ($affiliates as $affiliate) {
            $weeksWithSales = 0;
            $currentWeek = $from->copy()->startOfWeek();

            while ($currentWeek->lte($to)) {
                $weekEnd = $currentWeek->copy()->endOfWeek();

                $conversionsThisWeek = $this->statistics->count(
                    [$affiliate->id],
                    app(MerchantIdentity::class)->ownerTable()->connectionIdentity,
                    max($currentWeek, $from),
                    min($weekEnd, $to),
                    [ConversionStatus::Approved->value],
                    $includeGlobal,
                    inclusiveUntil: true,
                );

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

        $affiliates = app(AffiliateBonusReader::class)->candidates($includeGlobal);

        $floorCurrency = mb_strtoupper((string) ($config['min_previous_revenue_currency'] ?? config('affiliates.currency.default', 'MYR')));

        foreach ($affiliates as $affiliate) {
            $currentRevenue = RevenueVolume::measurableIn(
                $this->revenueByCurrency($affiliate, $from, $to, $includeGlobal),
                $floorCurrency,
                $to,
            );

            $previousRevenue = RevenueVolume::measurableIn(
                $this->revenueByCurrency($affiliate, $prevFrom, $prevTo, $includeGlobal),
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

    /** @return array<string, int> */
    private function revenueByCurrency(AffiliateBonusCandidate $affiliate, CarbonImmutable $from, CarbonImmutable $to, bool $includeGlobal): array
    {
        $totals = [];

        foreach ($this->statistics->totals(
            $affiliate->id,
            $affiliate->currency !== null && mb_trim($affiliate->currency) !== '' ? mb_strtoupper(mb_trim($affiliate->currency)) : RevenueVolume::default(),
            app(MerchantIdentity::class)->ownerTable()->connectionIdentity,
            $from,
            $to,
            [ConversionStatus::Approved->value],
            $includeGlobal,
            inclusiveUntil: true,
        ) as $row) {
            $totals[$row->currency] = $row->revenueMinor;
        }

        return $totals;
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
        $default = RevenueVolume::default();
        $entries = [];

        foreach ($this->reporting->approvedCommercialByAffiliate($from, $to, $includeGlobal) as $row) {
            $id = (string) $row->affiliateId;

            $entries[$id] ??= [
                'affiliate_id' => $id,
                'affiliate_name' => (string) $row->affiliateName,
                'total_conversions' => 0,
                'by_currency' => [],
            ];

            $entries[$id]['total_conversions'] += (int) $row->conversions;

            $currency = is_string($row->currency) && mb_trim($row->currency) !== ''
                ? mb_strtoupper(mb_trim($row->currency))
                : $default;

            $entries[$id]['by_currency'][$currency] = ($entries[$id]['by_currency'][$currency] ?? 0) + (int) $row->revenueMinor;
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
