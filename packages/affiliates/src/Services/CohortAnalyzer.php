<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Analyzes affiliate cohorts by acquisition date to track lifetime value and performance trends.
 */
final class CohortAnalyzer
{
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
     *     monthly_breakdown: array<string, array{
     *         month: int,
     *         active: int,
     *         conversions: int,
     *         revenue: int,
     *         commissions: int
     *     }>
     * }>
     */
    public function analyzeMonthly(
        ?Carbon $from = null,
        ?Carbon $to = null,
        int $monthsToTrack = 12
    ): array {
        $from = $from ?? now()->subYear()->startOfMonth();
        $to = $to ?? now()->endOfMonth();

        $cohorts = $this->getCohorts($from, $to);
        $results = [];

        foreach ($cohorts as $cohortMonth => $affiliateIds) {
            $cohortDate = Carbon::parse($cohortMonth . '-01');
            $affiliates = Affiliate::whereIn('id', $affiliateIds)->get();

            $monthlyBreakdown = $this->calculateMonthlyBreakdown(
                $affiliateIds,
                $cohortDate,
                $monthsToTrack
            );

            $totalConversions = $affiliates->sum(function ($affiliate) {
                return $affiliate->conversions()->count();
            });

            $totalRevenue = $affiliates->sum(function ($affiliate) {
                return $affiliate->conversions()->sum('total_minor');
            });

            $totalCommissions = $affiliates->sum(function ($affiliate) {
                return $affiliate->conversions()->sum('commission_minor');
            });

            $activeAffiliates = $affiliates->where('status', AffiliateStatus::Active)->count();

            $results[$cohortMonth] = [
                'cohort' => $cohortMonth,
                'total_affiliates' => count($affiliateIds),
                'active_affiliates' => $activeAffiliates,
                'retention_rate' => count($affiliateIds) > 0
                    ? round(($activeAffiliates / count($affiliateIds)) * 100, 2)
                    : 0,
                'total_conversions' => $totalConversions,
                'total_revenue' => $totalRevenue,
                'total_commissions' => $totalCommissions,
                'avg_revenue_per_affiliate' => count($affiliateIds) > 0
                    ? round($totalRevenue / count($affiliateIds), 2)
                    : 0,
                'avg_conversions_per_affiliate' => count($affiliateIds) > 0
                    ? round($totalConversions / count($affiliateIds), 2)
                    : 0,
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
        ?Carbon $from = null,
        ?Carbon $to = null,
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
        ?Carbon $from = null,
        ?Carbon $to = null
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
    public function compareCohorts(?Carbon $from = null, ?Carbon $to = null): array
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
    public function analyzeBySource(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->subYear();
        $to = $to ?? now();

        $affiliatesTable = (new Affiliate)->getTable();
        $conversionsTable = (new AffiliateConversion)->getTable();
        $driver = DB::connection()->getDriverName();

        $sourceExpression = $driver === 'sqlite'
            ? "COALESCE(json_extract(a.metadata, '$.source'), 'direct')"
            : "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.metadata, '$.source')), 'direct')";

        $query = DB::table("{$affiliatesTable} as a")
            ->leftJoin("{$conversionsTable} as c", 'c.affiliate_id', '=', 'a.id')
            ->selectRaw("{$sourceExpression} as source")
            ->selectRaw('COUNT(DISTINCT a.id) as total_affiliates')
            ->selectRaw('COUNT(c.id) as total_conversions')
            ->selectRaw('COUNT(DISTINCT c.affiliate_id) as with_conversions')
            ->selectRaw('COALESCE(SUM(c.total_minor), 0) as total_revenue')
            ->whereBetween('a.created_at', [$from, $to])
            ->groupBy('source');

        $this->applyOwnerScopeToQuery($query, 'a.owner_type', 'a.owner_id');
        $this->applyOwnerScopeToQuery($query, 'c.owner_type', 'c.owner_id');

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
     * Get affiliate IDs grouped by acquisition month.
     *
     * @return Collection<string, array<int, string>>
     */
    private function getCohorts(Carbon $from, Carbon $to): Collection
    {
        $affiliatesTable = (new Affiliate)->getTable();
        $driver = DB::connection()->getDriverName();

        $dateFormat = $driver === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $cohortQuery = DB::table($affiliatesTable)
            ->select('id', DB::raw("$dateFormat as cohort_month"))
            ->whereBetween('created_at', [$from, $to]);

        $this->applyOwnerScopeToQuery($cohortQuery, "{$affiliatesTable}.owner_type", "{$affiliatesTable}.owner_id");

        return $cohortQuery->get()
            ->groupBy('cohort_month')
            ->map(fn ($group) => $group->pluck('id')->toArray());
    }

    /**
     * Calculate monthly breakdown for a cohort.
     *
     * @param  array<string>  $affiliateIds
     * @return array<int, array{month: int, active: int, conversions: int, revenue: int, commissions: int}>
     */
    private function calculateMonthlyBreakdown(
        array $affiliateIds,
        Carbon $cohortDate,
        int $monthsToTrack
    ): array {
        $breakdown = [];

        for ($month = 0; $month < $monthsToTrack; $month++) {
            $periodStart = $cohortDate->copy()->addMonths($month)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();

            if ($periodStart->isFuture()) {
                break;
            }

            $activeCount = Affiliate::whereIn('id', $affiliateIds)
                ->where('status', AffiliateStatus::Active)
                ->where(function ($query) use ($periodEnd): void {
                    $query->whereNull('disabled_at')
                        ->orWhere('disabled_at', '>', $periodEnd);
                })
                ->count();

            $conversionsTable = (new AffiliateConversion)->getTable();
            $conversionsQuery = DB::table($conversionsTable)
                ->whereIn('affiliate_id', $affiliateIds)
                ->whereBetween('occurred_at', [$periodStart, $periodEnd])
                ->selectRaw('COUNT(*) as count, SUM(total_minor) as revenue, SUM(commission_minor) as commissions');

            $this->applyOwnerScopeToQuery($conversionsQuery, "{$conversionsTable}.owner_type", "{$conversionsTable}.owner_id");

            $conversions = $conversionsQuery->first();

            $breakdown[$month] = [
                'month' => $month,
                'active' => $activeCount,
                'conversions' => (int) ($conversions->count ?? 0),
                'revenue' => (int) ($conversions->revenue ?? 0),
                'commissions' => (int) ($conversions->commissions ?? 0),
            ];
        }

        return $breakdown;
    }

    private function applyOwnerScopeToQuery($query, string $ownerTypeColumn, string $ownerIdColumn): void
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return;
        }

        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('affiliates.owner.include_global', false);

        OwnerQuery::applyToQueryBuilder($query, $owner, $includeGlobal, $ownerTypeColumn, $ownerIdColumn);
    }
}
