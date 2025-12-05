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
        $clicks = AffiliateTouchpoint::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereDate('touched_at', $date)
            ->count();

        $uniqueClicks = AffiliateTouchpoint::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereDate('touched_at', $date)
            ->distinct('ip_address')
            ->count('ip_address');

        $attributions = $affiliate->attributions()
            ->whereDate('first_seen_at', $date)
            ->count();

        $conversions = $affiliate->conversions()
            ->whereDate('occurred_at', $date);

        $conversionCount = $conversions->count();
        $revenue = (int) $conversions->sum('total_minor');
        $commission = (int) $conversions->sum('commission_minor');

        $conversionRate = $clicks > 0 ? $conversionCount / $clicks : 0;
        $epc = $clicks > 0 ? $commission / $clicks : 0;

        $breakdown = $this->buildBreakdown($affiliate, $date);

        return AffiliateDailyStat::updateOrCreate(
            [
                'affiliate_id' => $affiliate->id,
                'date' => $date->toDateString(),
            ],
            [
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
