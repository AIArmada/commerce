<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class AffiliateReportService
{
    /**
     * @return array{attributions: int, conversions: int, revenue_minor: int, commission_minor: int}
     */
    public function getSummary(Carbon $startDate, Carbon $endDate): array
    {
        $conversions = AffiliateConversion::query()
            ->forOwner()
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->get(['commission_minor', 'total_minor']);

        $attributions = (int) AffiliateAttribution::query()
            ->forOwner()
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->count();

        return [
            'attributions' => $attributions,
            'conversions' => $conversions->count(),
            'revenue_minor' => (int) $conversions->sum('total_minor'),
            'commission_minor' => (int) $conversions->sum('commission_minor'),
        ];
    }

    /**
     * @return array<int, array{affiliate_id: string, affiliate_code: string, name: string|null, conversions: int, revenue_minor: int, commission_minor: int}>
     */
    public function getTopAffiliates(Carbon $startDate, Carbon $endDate, int $limit = 10): array
    {
        $rows = AffiliateConversion::query()
            ->forOwner()
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->toBase()
            ->selectRaw('affiliate_id, MAX(affiliate_code) as affiliate_code, COUNT(*) as conversions, SUM(total_minor) as revenue_minor, SUM(commission_minor) as commission_minor')
            ->groupBy('affiliate_id')
            ->orderByDesc('commission_minor')
            ->limit($limit)
            ->get();

        $affiliateNamesById = Affiliate::query()
            ->forOwner()
            ->whereIn('id', $rows->pluck('affiliate_id')->all())
            ->pluck('name', 'id');

        return $rows
            ->map(fn (object $row): array => [
                'affiliate_id' => (string) $row->affiliate_id,
                'affiliate_code' => (string) $row->affiliate_code,
                'name' => $affiliateNamesById[(string) $row->affiliate_id] ?? null,
                'conversions' => (int) $row->conversions,
                'revenue_minor' => (int) $row->revenue_minor,
                'commission_minor' => (int) $row->commission_minor,
            ])
            ->all();
    }

    /**
     * @return array<int, array{date: string, conversions: int, revenue_minor: int, commission_minor: int}>
     */
    public function getConversionTrend(Carbon $startDate, Carbon $endDate): array
    {
        $rows = AffiliateConversion::query()
            ->forOwner()
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->toBase()
            ->selectRaw('DATE(occurred_at) as date, COUNT(*) as conversions, SUM(total_minor) as revenue_minor, SUM(commission_minor) as commission_minor')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $rows
            ->map(fn (object $row): array => [
                'date' => (string) $row->date,
                'conversions' => (int) $row->conversions,
                'revenue_minor' => (int) $row->revenue_minor,
                'commission_minor' => (int) $row->commission_minor,
            ])
            ->all();
    }

    /**
     * @return array{sources: array<string, int>, campaigns: array<string, int>}
     */
    public function getTrafficSources(Carbon $startDate, Carbon $endDate): array
    {
        $conversions = AffiliateConversion::query()
            ->forOwner()
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->get(['metadata']);

        return $this->aggregateUtm($conversions);
    }

    /**
     * @return array<string, mixed>
     */
    public function affiliateSummary(string $affiliateId): array
    {
        /** @var Affiliate|null $affiliate */
        $affiliate = Affiliate::query()->forOwner()->find($affiliateId);

        if (! $affiliate) {
            return [];
        }

        $conversions = AffiliateConversion::query()
            ->forOwner()
            ->where('affiliate_id', $affiliateId)
            ->get();

        $totalCommission = (int) $conversions->sum('commission_minor');
        $totalRevenue = (int) $conversions->sum('total_minor');
        $conversionCount = $conversions->count();
        $ltv = $conversionCount > 0 ? ($totalRevenue / $conversionCount) : 0;

        $utm = $this->aggregateUtm($conversions);
        $attributionCount = (int) AffiliateAttribution::query()
            ->forOwner()
            ->where('affiliate_id', $affiliateId)
            ->count();

        $funnel = [
            'attributions' => $attributionCount,
            'conversions' => $conversionCount,
            'conversion_rate' => $conversionCount > 0 && $attributionCount > 0
                ? round(($conversionCount / $attributionCount) * 100, 2)
                : 0,
        ];

        return [
            'affiliate' => [
                'id' => $affiliate->getKey(),
                'code' => $affiliate->code,
                'name' => $affiliate->name,
            ],
            'totals' => [
                'commission_minor' => $totalCommission,
                'revenue_minor' => $totalRevenue,
                'conversions' => $conversionCount,
                'ltv_minor' => (int) $ltv,
            ],
            'funnel' => $funnel,
            'utm' => $utm,
        ];
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function aggregateUtm(Collection $conversions): array
    {
        $sources = [];
        $campaigns = [];

        foreach ($conversions as $conversion) {
            $source = $conversion->metadata['source'] ?? null;
            $campaign = $conversion->metadata['campaign'] ?? null;

            if ($source) {
                $sources[$source] = ($sources[$source] ?? 0) + 1;
            }

            if ($campaign) {
                $campaigns[$campaign] = ($campaigns[$campaign] ?? 0) + 1;
            }
        }

        return [
            'sources' => $sources,
            'campaigns' => $campaigns,
        ];
    }
}
