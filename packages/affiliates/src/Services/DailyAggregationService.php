<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateDailyStat;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use Illuminate\Support\Carbon;

final class DailyAggregationService
{
    /**
     * Aggregate statistics for all affiliates on a given date.
     */
    public function aggregate(Carbon $date): int
    {
        $affiliateCount = 0;

        Affiliate::query()
            ->chunkById(100, function ($affiliates) use ($date, &$affiliateCount): void {
                foreach ($affiliates as $affiliate) {
                    $this->aggregateForAffiliate($affiliate, $date);
                    $affiliateCount++;
                }
            });

        return $affiliateCount;
    }

    /**
     * Aggregate statistics for a specific affiliate on a date.
     */
    public function aggregateForAffiliate(Affiliate $affiliate, Carbon $date): AffiliateDailyStat
    {
        $touchpointStats = AffiliateTouchpoint::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereDate('touched_at', $date)
            ->selectRaw('COUNT(*) as clicks, COUNT(DISTINCT ip_address) as unique_clicks')
            ->first();

        $clicks = (int) ($touchpointStats?->getAttribute('clicks') ?? 0);
        $uniqueClicks = (int) ($touchpointStats?->getAttribute('unique_clicks') ?? 0);

        $attributions = $affiliate->attributions()
            ->whereDate('first_seen_at', $date)
            ->count();

        $conversionStats = $affiliate->conversions()
            ->whereDate('occurred_at', $date)
            ->selectRaw('COUNT(*) as conversion_count, COALESCE(SUM(total_minor), 0) as revenue_minor, COALESCE(SUM(commission_minor), 0) as commission_minor')
            ->first();

        $conversionCount = (int) ($conversionStats?->getAttribute('conversion_count') ?? 0);
        $revenue = (int) ($conversionStats?->getAttribute('revenue_minor') ?? 0);
        $commission = (int) ($conversionStats?->getAttribute('commission_minor') ?? 0);

        $conversionRate = $clicks > 0 ? $conversionCount / $clicks : 0;
        $epc = $clicks > 0 ? $commission / $clicks : 0;

        $breakdown = $this->buildBreakdown($affiliate, $date);

        return AffiliateDailyStat::updateOrCreate(
            [
                'affiliate_id' => $affiliate->id,
                'date' => $date->toDateString(),
            ],
            [
                'owner_type' => $affiliate->owner_type,
                'owner_id' => $affiliate->owner_id,
                'clicks' => $clicks,
                'unique_clicks' => $uniqueClicks,
                'attributions' => $attributions,
                'conversions' => $conversionCount,
                'revenue_cents' => $revenue,
                'commission_cents' => $commission,
                'refunds' => 0,
                'refund_amount_cents' => 0,
                'conversion_rate' => $conversionRate,
                'epc_cents' => $epc,
                'breakdown' => $breakdown,
            ]
        );
    }

    /**
     * Backfill statistics for a date range.
     */
    public function backfill(Carbon $startDate, Carbon $endDate): int
    {
        $totalProcessed = 0;
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $totalProcessed += $this->aggregate($currentDate);
            $currentDate->addDay();
        }

        return $totalProcessed;
    }

    /**
     * Get aggregated stats for an affiliate over a period.
     *
     * @return array<string, mixed>
     */
    public function getAggregatedStats(Affiliate $affiliate, Carbon $from, Carbon $to): array
    {
        $stats = AffiliateDailyStat::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get();

        return [
            'clicks' => $stats->sum('clicks'),
            'unique_clicks' => $stats->sum('unique_clicks'),
            'attributions' => $stats->sum('attributions'),
            'conversions' => $stats->sum('conversions'),
            'revenue_cents' => $stats->sum('revenue_cents'),
            'commission_cents' => $stats->sum('commission_cents'),
            'conversion_rate' => $stats->avg('conversion_rate'),
            'epc_cents' => $stats->avg('epc_cents'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBreakdown(Affiliate $affiliate, Carbon $date): array
    {
        $bySource = AffiliateTouchpoint::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereDate('touched_at', $date)
            ->selectRaw('source, COUNT(*) as count')
            ->groupBy('source')
            ->pluck('count', 'source')
            ->toArray();

        $byCampaign = AffiliateTouchpoint::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereDate('touched_at', $date)
            ->selectRaw('campaign, COUNT(*) as count')
            ->groupBy('campaign')
            ->pluck('count', 'campaign')
            ->toArray();

        return [
            'by_source' => $bySource,
            'by_campaign' => $byCampaign,
        ];
    }
}
