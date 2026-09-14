<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateDailyStat;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class DailyAggregationService
{
    /**
     * Aggregate statistics for all affiliates on a given date.
     */
    public function aggregate(CarbonImmutable $date): int
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
    public function aggregateForAffiliate(Affiliate $affiliate, CarbonImmutable $date): AffiliateDailyStat
    {
        $dayStart = $date->startOfDay();
        $dayEnd = $date->endOfDay();

        $touchpointStats = AffiliateTouchpoint::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereBetween('touched_at', [$dayStart, $dayEnd])
            ->selectRaw('COUNT(*) as clicks, COUNT(DISTINCT ip_address) as unique_clicks')
            ->first();

        $clicks = (int) ($touchpointStats?->getAttribute('clicks') ?? 0);
        $uniqueClicks = (int) ($touchpointStats?->getAttribute('unique_clicks') ?? 0);

        $attributions = $affiliate->attributions()
            ->whereBetween('first_seen_at', [$dayStart, $dayEnd])
            ->count();

        $conversionStats = $affiliate->conversions()
            ->whereBetween('occurred_at', [$dayStart, $dayEnd])
            ->selectRaw(sprintf(
                'COUNT(*) as conversion_count, COALESCE(SUM(%s), 0) as revenue_minor, COALESCE(SUM(commission_minor), 0) as commission_minor',
                $this->revenueMinorExpression(),
            ))
            ->first();

        $conversionCount = (int) ($conversionStats?->getAttribute('conversion_count') ?? 0);
        $revenue = (int) ($conversionStats?->getAttribute('revenue_minor') ?? 0);
        $commission = (int) ($conversionStats?->getAttribute('commission_minor') ?? 0);

        $conversionRate = $clicks > 0 ? $conversionCount / $clicks : 0;
        $epc = $clicks > 0 ? $commission / $clicks : 0;

        $breakdown = $this->buildBreakdown($affiliate, $dayStart, $dayEnd);
        $now = CarbonImmutable::now();

        AffiliateDailyStat::upsert(
            [
                [
                    'id' => (string) Str::uuid(),
                    'affiliate_id' => $affiliate->id,
                    'date' => $date->toDateString(),
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
                    'breakdown' => json_encode($breakdown, JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['affiliate_id', 'date'],
            ['owner_type', 'owner_id', 'clicks', 'unique_clicks', 'attributions', 'conversions', 'revenue_cents', 'commission_cents', 'conversion_rate', 'epc_cents', 'breakdown', 'updated_at'],
        );

        return AffiliateDailyStat::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('date', $date->toDateString())
            ->firstOrFail();
    }

    /**
     * Backfill statistics for a date range.
     */
    public function backfill(CarbonImmutable $startDate, CarbonImmutable $endDate): int
    {
        $totalProcessed = 0;
        $currentDate = $startDate;

        while ($currentDate->lte($endDate)) {
            $totalProcessed += $this->aggregate($currentDate);
            $currentDate = $currentDate->addDay();
        }

        return $totalProcessed;
    }

    /**
     * Get aggregated stats for an affiliate over a period.
     *
     * @return array<string, mixed>
     */
    public function getAggregatedStats(Affiliate $affiliate, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $totals = AffiliateDailyStat::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->toBase()
            ->selectRaw('COALESCE(SUM(clicks), 0) as clicks, COALESCE(SUM(unique_clicks), 0) as unique_clicks, COALESCE(SUM(attributions), 0) as attributions, COALESCE(SUM(conversions), 0) as conversions, COALESCE(SUM(revenue_cents), 0) as revenue_cents, COALESCE(SUM(commission_cents), 0) as commission_cents, AVG(conversion_rate) as conversion_rate, AVG(epc_cents) as epc_cents')
            ->first();

        return [
            'clicks' => (int) ($totals->clicks ?? 0),
            'unique_clicks' => (int) ($totals->unique_clicks ?? 0),
            'attributions' => (int) ($totals->attributions ?? 0),
            'conversions' => (int) ($totals->conversions ?? 0),
            'revenue_cents' => (int) ($totals->revenue_cents ?? 0),
            'commission_cents' => (int) ($totals->commission_cents ?? 0),
            'conversion_rate' => $totals->conversion_rate !== null ? (float) $totals->conversion_rate : null,
            'epc_cents' => $totals->epc_cents !== null ? (float) $totals->epc_cents : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBreakdown(Affiliate $affiliate, CarbonImmutable $dayStart, CarbonImmutable $dayEnd): array
    {
        $rows = AffiliateTouchpoint::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereBetween('touched_at', [$dayStart, $dayEnd])
            ->selectRaw('source, campaign, COUNT(*) as count')
            ->groupBy('source', 'campaign')
            ->get();

        $bySource = [];
        $byCampaign = [];

        foreach ($rows as $row) {
            $count = (int) $row->getAttribute('count');

            if (is_string($row->getAttribute('source'))) {
                $bySource[$row->getAttribute('source')] = ($bySource[$row->getAttribute('source')] ?? 0) + $count;
            }

            if (is_string($row->getAttribute('campaign'))) {
                $byCampaign[$row->getAttribute('campaign')] = ($byCampaign[$row->getAttribute('campaign')] ?? 0) + $count;
            }
        }

        return [
            'by_source' => $bySource,
            'by_campaign' => $byCampaign,
        ];
    }

    private function revenueMinorExpression(): string
    {
        return 'COALESCE(value_minor, 0)';
    }
}
