<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Reporting\Queries;

use AIArmada\Affiliates\Features\Attribution\Api\AttributionReportingReader;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionStatisticsReader;
use AIArmada\Affiliates\Features\Reporting\Api\CohortApi;
use AIArmada\Affiliates\Contracts\MerchantIdentity;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateCohortMember;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateReportingReader;
use AIArmada\Affiliates\Features\Reporting\Models\AffiliateDailyStat;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use Illuminate\Database\Connection;
use AIArmada\CommerceSupport\Support\ConnectionDriver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Analyzes affiliate cohorts by acquisition date to track lifetime value and performance trends.
 */
final class CohortQuery implements CohortApi
{
    public function __construct(private readonly AttributionReportingReader $attributions, private readonly ConversionStatisticsReader $conversions) {}

    /**
     * Analyze cohorts by month of acquisition.
     *
     * @return array<string, array{
     *     cohort: string,
     *     total_affiliates: int,
     *     active_affiliates: int,
     *     retention_rate: float,
     *     total_conversions: int,
     *     total_revenue: int,
     *     total_commissions: int,
     *     avg_revenue_per_affiliate: float,
     *     avg_conversions_per_affiliate: float,
     *     monthly_breakdown: array<int, array{
     *         month: int,
     *         active: int,
     *         conversions: int,
     *         revenue: int,
     *         commissions: int
     *     }>
     * }>
     */
    public function analyzeMonthly(
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
        int $monthsToTrack = 12
    ): array {
        $from = $from ?? CarbonImmutable::now()->subYear()->startOfMonth();
        $to = $to ?? CarbonImmutable::now()->endOfMonth();

        $cohorts = app(AffiliateReportingReader::class)->cohorts($from, $to);
        $results = [];

        $allAffiliateIds = collect($cohorts)->flatten(1)->map(static fn (AffiliateCohortMember $member): string => $member->id)->unique()->values()->all();
        $totalsByAffiliate = $this->getConversionTotalsByAffiliate($allAffiliateIds);

        foreach ($cohorts as $cohortMonth => $members) {
            $cohortDate = CarbonImmutable::parse($cohortMonth . '-01');
            $affiliates = collect($members);
            $affiliateIds = $affiliates->map(static fn (AffiliateCohortMember $member): string => $member->id)->all();

            $monthlyBreakdown = $this->calculateMonthlyBreakdown(
                $affiliateIds,
                $cohortDate,
                $monthsToTrack,
                $affiliates
            );

            $totalConversions = 0;
            $totalRevenue = 0;
            $totalCommissions = 0;

            foreach ($affiliateIds as $affiliateId) {
                $totals = $totalsByAffiliate[(string) $affiliateId] ?? null;

                if ($totals === null) {
                    continue;
                }

                $totalConversions += $totals['count'];
                $totalRevenue += $totals['revenue'];
                $totalCommissions += $totals['commissions'];
            }

            $activeAffiliates = $affiliates
                ->filter(fn (AffiliateCohortMember $affiliate): bool => $affiliate->active)
                ->count();

            $results[$cohortMonth] = [
                'cohort' => $cohortMonth,
                'total_affiliates' => count($affiliateIds),
                'active_affiliates' => $activeAffiliates,
                'retention_rate' => count($affiliateIds) > 0
                    ? round(($activeAffiliates / count($affiliateIds)) * 100, 2)
                    : 0.0,
                'total_conversions' => $totalConversions,
                'total_revenue' => $totalRevenue,
                'total_commissions' => $totalCommissions,
                'avg_revenue_per_affiliate' => count($affiliateIds) > 0
                    ? round($totalRevenue / count($affiliateIds), 2)
                    : 0.0,
                'avg_conversions_per_affiliate' => count($affiliateIds) > 0
                    ? round($totalConversions / count($affiliateIds), 2)
                    : 0.0,
                'monthly_breakdown' => $monthlyBreakdown,
            ];
        }

        return $results;
    }

    /**
     * Calculate retention curve across all cohorts.
     *
     * @return array<int, array{
     *     month: int,
     *     avg_retention: float,
     *     avg_revenue: float,
     *     avg_conversions: float,
     *     sample_size: int
     * }>
     */
    public function calculateRetentionCurve(
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
        int $maxMonths = 12
    ): array {
        $cohortData = $this->analyzeMonthly($from, $to, $maxMonths);
        $retentionCurve = [];

        for ($month = 0; $month < $maxMonths; $month++) {
            $retentionRates = [];
            $revenues = [];
            $conversions = [];

            foreach ($cohortData as $cohort) {
                if (isset($cohort['monthly_breakdown'][$month])) {
                    $monthData = $cohort['monthly_breakdown'][$month];
                    $retentionRate = $cohort['total_affiliates'] > 0
                        ? ($monthData['active'] / $cohort['total_affiliates']) * 100
                        : 0;

                    $retentionRates[] = $retentionRate;
                    $revenues[] = $monthData['revenue'];
                    $conversions[] = $monthData['conversions'];
                }
            }

            $sampleSize = count($retentionRates);

            $retentionCurve[$month] = [
                'month' => $month,
                'avg_retention' => $sampleSize > 0
                    ? round(array_sum($retentionRates) / $sampleSize, 2)
                    : 0,
                'avg_revenue' => $sampleSize > 0
                    ? round(array_sum($revenues) / $sampleSize, 2)
                    : 0,
                'avg_conversions' => $sampleSize > 0
                    ? round(array_sum($conversions) / $sampleSize, 2)
                    : 0,
                'sample_size' => $sampleSize,
            ];
        }

        return $retentionCurve;
    }

    /**
     * Get lifetime value (LTV) by cohort.
     *
     * @return array<string, array{
     *     cohort: string,
     *     ltv: float,
     *     months_tracked: int,
     *     avg_monthly_revenue: float,
     *     projected_annual_ltv: float
     * }>
     */
    public function calculateLtv(
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null
    ): array {
        $cohortData = $this->analyzeMonthly($from, $to);
        $ltvData = [];

        foreach ($cohortData as $cohortMonth => $data) {
            $monthsTracked = count($data['monthly_breakdown']);
            $avgMonthlyRevenue = $monthsTracked > 0
                ? $data['total_revenue'] / $monthsTracked
                : 0;

            $ltvData[$cohortMonth] = [
                'cohort' => $cohortMonth,
                'ltv' => $data['avg_revenue_per_affiliate'],
                'months_tracked' => $monthsTracked,
                'avg_monthly_revenue' => round($avgMonthlyRevenue, 2),
                'projected_annual_ltv' => round($avgMonthlyRevenue * 12, 2),
            ];
        }

        return $ltvData;
    }

    /**
     * Compare cohort performance.
     *
     * @return array{
     *     best_cohort: string|null,
     *     worst_cohort: string|null,
     *     avg_ltv: float,
     *     avg_retention: float,
     *     trend: string
     * }
     */
    public function compareCohorts(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $cohortData = $this->analyzeMonthly($from, $to);

        if (empty($cohortData)) {
            return [
                'best_cohort' => null,
                'worst_cohort' => null,
                'avg_ltv' => 0,
                'avg_retention' => 0,
                'trend' => 'no_data',
            ];
        }

        $ltvValues = [];
        $retentionValues = [];

        foreach ($cohortData as $cohortMonth => $data) {
            $ltvValues[$cohortMonth] = $data['avg_revenue_per_affiliate'];
            $retentionValues[$cohortMonth] = $data['retention_rate'];
        }

        $bestCohort = array_search(max($ltvValues), $ltvValues);
        $worstCohort = array_search(min($ltvValues), $ltvValues);

        // Calculate trend (improving/declining/stable)
        $cohortMonths = array_keys($cohortData);
        $recentCohorts = array_slice($cohortMonths, -3, 3);
        $olderCohorts = array_slice($cohortMonths, 0, 3);

        $recentAvgLtv = 0;
        $olderAvgLtv = 0;

        foreach ($recentCohorts as $month) {
            $recentAvgLtv += $ltvValues[$month] ?? 0;
        }

        foreach ($olderCohorts as $month) {
            $olderAvgLtv += $ltvValues[$month] ?? 0;
        }

        $recentAvgLtv = count($recentCohorts) > 0 ? $recentAvgLtv / count($recentCohorts) : 0;
        $olderAvgLtv = count($olderCohorts) > 0 ? $olderAvgLtv / count($olderCohorts) : 0;

        $trend = 'stable';
        if ($olderAvgLtv > 0) {
            $change = (($recentAvgLtv - $olderAvgLtv) / $olderAvgLtv) * 100;
            if ($change > 10) {
                $trend = 'improving';
            } elseif ($change < -10) {
                $trend = 'declining';
            }
        }

        return [
            'best_cohort' => $bestCohort !== false ? $bestCohort : null,
            'worst_cohort' => $worstCohort !== false ? $worstCohort : null,
            'avg_ltv' => round(array_sum($ltvValues) / count($ltvValues), 2),
            'avg_retention' => round(array_sum($retentionValues) / count($retentionValues), 2),
            'trend' => $trend,
        ];
    }

    /**
     * Get cohort breakdown by acquisition source.
     *
     * @return array<string, array{
     *     source: string,
     *     total_affiliates: int,
     *     total_revenue: int,
     *     avg_ltv: float,
     *     conversion_rate: float
     * }>
     */
    public function analyzeBySource(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $from = $from ?? CarbonImmutable::now()->subYear();
        $to = $to ?? CarbonImmutable::now();

        $affiliatesTable = app(MerchantIdentity::class)->ownerTable()->table;
        $conversionsTable = $this->conversionTable();
        $connection = $this->connection();

        $this->attributions->assertSharedConnection(spl_object_id($connection));
        $attributionsTable = (string) config('affiliates.database.tables.attributions', 'affiliate_attributions');
        $sourceExpression = "COALESCE(aa.source, 'direct')";
        $revenueExpression = 'COALESCE(c.value_minor, 0)';

        $query = $connection->table("{$affiliatesTable} as a")
            ->leftJoin("{$conversionsTable} as c", function (JoinClause $join): void {
                $join->on('c.affiliate_id', '=', 'a.id');
                $this->applyConversionsOwnerScope($join, 'c');
            })
            ->leftJoin("{$attributionsTable} as aa", function (JoinClause $join): void {
                $join->on('aa.id', '=', 'c.affiliate_attribution_id');
                $this->applyConversionsOwnerScope($join, 'aa');
            })
            ->selectRaw("{$sourceExpression} as source")
            ->selectRaw('COUNT(DISTINCT a.id) as total_affiliates')
            ->selectRaw('COUNT(c.id) as total_conversions')
            ->selectRaw('COUNT(DISTINCT c.affiliate_id) as with_conversions')
            ->selectRaw("COALESCE(SUM({$revenueExpression}), 0) as total_revenue")
            ->whereBetween('a.created_at', [$from, $to])
            ->groupBy('source');

        if ((bool) config('affiliates.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                'Cohort queries require an owner context or explicit global context.',
            );
            $includeGlobal = (bool) config('affiliates.owner.include_global', false) && ! OwnerScopeOverride::suppressIncludeGlobal();

            OwnerQuery::applyToQueryBuilder($query, $owner, $includeGlobal, 'a.owner_type', 'a.owner_id');
        }

        /** @var array<int, object{source: string, total_affiliates: int|string, total_conversions: int|string, with_conversions: int|string, total_revenue: int|string}> $rows */
        $rows = $query->get()->all();

        $results = [];

        foreach ($rows as $row) {
            $affiliateCount = (int) $row->total_affiliates;
            $totalRevenue = (int) $row->total_revenue;
            $withConversions = (int) $row->with_conversions;

            $results[$row->source] = [
                'source' => $row->source,
                'total_affiliates' => $affiliateCount,
                'total_revenue' => $totalRevenue,
                'avg_ltv' => $affiliateCount > 0 ? round($totalRevenue / $affiliateCount, 2) : 0,
                'conversion_rate' => $affiliateCount > 0
                    ? round(($withConversions / $affiliateCount) * 100, 2)
                    : 0,
            ];
        }

        return $results;
    }

    /**
     * Calculate monthly breakdown for a cohort.
     *
     * Active counts are computed in memory from the already-loaded affiliates,
     * and conversions are fetched in one grouped query per cohort instead of
     * two queries per cohort-month.
     *
     * @param  array<string>  $affiliateIds
     * @param  Collection<int, AffiliateCohortMember>  $affiliates
     * @return array<int, array{month: int, active: int, conversions: int, revenue: int, commissions: int}>
     */
    private function calculateMonthlyBreakdown(
        array $affiliateIds,
        CarbonImmutable $cohortDate,
        int $monthsToTrack,
        Collection $affiliates
    ): array {
        $breakdown = [];

        $maxMonth = 0;

        for ($month = 0; $month < $monthsToTrack; $month++) {
            $periodStart = $cohortDate->copy()->addMonths($month)->startOfMonth();

            if ($periodStart->isFuture()) {
                break;
            }

            $maxMonth = $month + 1;
        }

        if ($maxMonth <= 0) {
            return $breakdown;
        }

        $conversionsByMonth = $this->getMonthlyConversionAggregates($affiliateIds, $cohortDate, $maxMonth);

        for ($month = 0; $month < $maxMonth; $month++) {
            $periodEnd = $cohortDate->copy()->addMonths($month)->endOfMonth();

            $activeCount = $affiliates
                ->filter(fn (AffiliateCohortMember $affiliate): bool => $affiliate->active
                    && ($affiliate->deactivatedAt === null || $affiliate->deactivatedAt->greaterThan($periodEnd)))
                ->count();

            $conversions = $conversionsByMonth[$month] ?? ['count' => 0, 'revenue' => 0, 'commissions' => 0];

            $breakdown[$month] = [
                'month' => $month,
                'active' => $activeCount,
                'conversions' => (int) $conversions['count'],
                'revenue' => (int) $conversions['revenue'],
                'commissions' => (int) $conversions['commissions'],
            ];
        }

        return $breakdown;
    }

    /**
     * Fetch lifetime conversion totals grouped by affiliate in one query.
     *
     * @param  array<int, string>  $affiliateIds
     * @return array<string, array{count: int, revenue: int, commissions: int}>
     */
    private function getConversionTotalsByAffiliate(array $affiliateIds): array
    {
        if ($affiliateIds === []) {
            return [];
        }

        $conversionsTable = $this->conversionTable();

        $query = $this->connection()->table($conversionsTable)
            ->whereIn('affiliate_id', $affiliateIds)
            ->select('affiliate_id')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(COALESCE(value_minor, 0)), 0) as revenue, COALESCE(SUM(commission_minor), 0) as commissions')
            ->groupBy('affiliate_id');

        $this->applyConversionsOwnerScope($query, $conversionsTable);

        $totals = [];

        foreach ($query->get() as $row) {
            $totals[(string) $row->affiliate_id] = [
                'count' => (int) ($row->count ?? 0),
                'revenue' => (int) ($row->revenue ?? 0),
                'commissions' => (int) ($row->commissions ?? 0),
            ];
        }

        return $totals;
    }

    /**
     * Fetch conversion aggregates grouped by cohort-relative month in one query.
     *
     * @param  array<string>  $affiliateIds
     * @return array<int, array{count: int, revenue: int, commissions: int}>
     */
    private function getMonthlyConversionAggregates(
        array $affiliateIds,
        CarbonImmutable $cohortDate,
        int $maxMonth
    ): array {
        if ($affiliateIds === [] || $maxMonth <= 0) {
            return [];
        }

        $conversionsTable = $this->conversionTable();
        $driver = ConnectionDriver::name($this->connection());

        $monthExpression = match ($driver) {
            'pgsql' => "to_char(occurred_at, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', occurred_at)",
            default => throw new RuntimeException("Unsupported database driver [{$driver}] for affiliate cohorts."),
        };

        $rangeStart = $cohortDate->copy()->startOfMonth();
        $rangeEnd = $cohortDate->copy()->addMonths($maxMonth - 1)->endOfMonth();

        $query = $this->connection()->table($conversionsTable)
            ->whereIn('affiliate_id', $affiliateIds)
            ->whereBetween('occurred_at', [$rangeStart, $rangeEnd])
            ->selectRaw("{$monthExpression} as month_key")
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(COALESCE(value_minor, 0)), 0) as revenue, COALESCE(SUM(commission_minor), 0) as commissions')
            ->groupBy('month_key');

        $this->applyConversionsOwnerScope($query, $conversionsTable);

        $aggregates = [];

        foreach ($query->get() as $row) {
            if (! isset($row->month_key) || ! is_string($row->month_key)) {
                continue;
            }

            $rowMonth = CarbonImmutable::parse($row->month_key . '-01');
            $index = ($rowMonth->year - $cohortDate->year) * 12 + ($rowMonth->month - $cohortDate->month);

            if ($index < 0 || $index >= $maxMonth) {
                continue;
            }

            $aggregates[$index] = [
                'count' => (int) ($row->count ?? 0),
                'revenue' => (int) ($row->revenue ?? 0),
                'commissions' => (int) ($row->commissions ?? 0),
            ];
        }

        return $aggregates;
    }

    private function connection(): Connection
    {
        $connection = (new AffiliateDailyStat)->getConnection();

        if (app(MerchantIdentity::class)->ownerTable()->connectionIdentity !== spl_object_id($connection)) {
            throw new RuntimeException('Cohort reporting requires a shared database connection.');
        }

        return $connection;
    }

    private function conversionTable(): string
    {
        $this->conversions->assertConnection(spl_object_id($this->connection()));

        return (string) config('affiliates.database.tables.conversions', 'affiliate_conversions');
    }

    private function applyConversionsOwnerScope(QueryBuilder $query, string $conversionsTable): void
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return;
        }

        $owner = OwnerContext::resolve();
        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            'Cohort queries require an owner context or explicit global context.',
        );

        OwnerQuery::applyToQueryBuilder(
            $query,
            $owner,
            (bool) config('affiliates.owner.include_global', false) && ! OwnerScopeOverride::suppressIncludeGlobal(),
            "{$conversionsTable}.owner_type",
            "{$conversionsTable}.owner_id",
        );
    }
}
