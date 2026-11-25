<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Reporting\Actions;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Features\Reporting\Models\AffiliateDailyStat;
use AIArmada\Affiliates\Features\Reporting\Api\DailyStatsWriter;
use AIArmada\Affiliates\Features\Reporting\Api\DailyStatRecord;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class AggregateDailyStats implements DailyStatsWriter
{
    public function __construct(private DeleteAffiliateDailyStats $delete) {}

    /**
     * Aggregate statistics for all affiliates on a given date.
     */
    public function aggregate(CarbonImmutable $date): int
    {
        $affiliateCount = 0;

        Affiliate::query()
            ->chunkById(100, function ($affiliates) use ($date, &$affiliateCount): void {
                foreach ($affiliates as $affiliate) {
                    $this->aggregateForAffiliate((string) $affiliate->getKey(), $date);
                    $affiliateCount++;
                }
            });

        return $affiliateCount;
    }

    /**
     * Aggregate statistics for a specific affiliate on a date.
     *
     * One row per active currency. Click-level counts live on the affiliate
     * currency row only so period sums never double-count; conversion counts
     * and money are per leg and sum cleanly.
     *
     * @return list<DailyStatRecord>
     */
    public function aggregateForAffiliate(string $affiliateId, CarbonImmutable $date): array
    {
        return (new AffiliateDailyStat)->getConnection()->transaction(function () use ($affiliateId, $date): array {
        $affiliate = Affiliate::query()->whereKey($affiliateId)->lockForUpdate()->firstOrFail();
        $dayStart = $date->startOfDay();
        $dayEnd = $date->endOfDay();
        $reference = $this->referenceCurrency($affiliate);

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

        $moneyRows = $affiliate->conversions()
            ->whereBetween('occurred_at', [$dayStart, $dayEnd])
            ->toBase()
            ->selectRaw(sprintf(
                'commission_currency as currency, COUNT(*) as conversion_count, COALESCE(SUM(%s), 0) as revenue_minor, COALESCE(SUM(commission_minor), 0) as commission_minor',
                $this->revenueMinorExpression(),
            ))
            ->groupBy('commission_currency')
            ->get();

        $legs = [];

        foreach ($moneyRows as $row) {
            $currency = $this->normalizeCurrency($row->currency ?? null, $reference);

            $legs[$currency] ??= ['conversions' => 0, 'revenue' => 0, 'commission' => 0];
            $legs[$currency]['conversions'] += (int) $row->conversion_count;
            $legs[$currency]['revenue'] += (int) $row->revenue_minor;
            $legs[$currency]['commission'] += (int) $row->commission_minor;
        }

        if ($legs === [] || ($clicks > 0 || $attributions > 0) && ! isset($legs[$reference])) {
            $legs[$reference] = ['conversions' => 0, 'revenue' => 0, 'commission' => 0];
        }

        $totalConversions = array_sum(array_column($legs, 'conversions'));
        $conversionRate = $clicks > 0 ? $totalConversions / $clicks : 0;
        $breakdown = json_encode($this->buildBreakdown($affiliate, $dayStart, $dayEnd), JSON_THROW_ON_ERROR);
        $now = CarbonImmutable::now();
        $upsertRows = [];

        foreach ($legs as $currency => $leg) {
            $primary = $currency === $reference;

            $upsertRows[] = [
                'id' => (string) Str::uuid(),
                'affiliate_id' => $affiliate->id,
                'date' => $date->toDateString(),
                'currency' => $currency,
                'owner_type' => $affiliate->owner_type,
                'owner_id' => $affiliate->owner_id,
                'clicks' => $primary ? $clicks : 0,
                'unique_clicks' => $primary ? $uniqueClicks : 0,
                'attributions' => $primary ? $attributions : 0,
                'conversions' => $leg['conversions'],
                'revenue_cents' => $leg['revenue'],
                'commission_cents' => $leg['commission'],
                'refunds' => 0,
                'refund_amount_cents' => 0,
                'conversion_rate' => $primary ? $conversionRate : 0,
                'epc_cents' => $clicks > 0 ? $leg['commission'] / $clicks : 0,
                'breakdown' => $primary ? $breakdown : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        AffiliateDailyStat::upsert(
            $upsertRows,
            ['affiliate_id', 'date', 'currency'],
            ['owner_type', 'owner_id', 'clicks', 'unique_clicks', 'attributions', 'conversions', 'revenue_cents', 'commission_cents', 'conversion_rate', 'epc_cents', 'breakdown', 'updated_at'],
        );

        AffiliateDailyStat::query()->where('affiliate_id', $affiliate->id)->where('date', $date->toDateString())->whereNotIn('currency', array_keys($legs))->delete();

        return AffiliateDailyStat::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('date', $date->toDateString())
            ->orderBy('currency')
            ->get()->map(static fn (AffiliateDailyStat $stat): DailyStatRecord => new DailyStatRecord(
                (string) $stat->getKey(), $stat->currency, $stat->clicks, $stat->unique_clicks, $stat->attributions, $stat->conversions, $stat->revenue_cents, $stat->commission_cents,
            ))->values()->all();
        }, attempts: 3);
    }

    /**
     * Recalculate derived statistics for a requested date range.
     */
    public function aggregateRange(CarbonImmutable $startDate, CarbonImmutable $endDate): int
    {
        $totalProcessed = 0;
        $currentDate = $startDate;

        while ($currentDate->lte($endDate)) {
            $totalProcessed += $this->aggregate($currentDate);
            $currentDate = $currentDate->addDay();
        }

        return $totalProcessed;
    }

    public function deleteForAffiliate(string $affiliateId): void
    {
        $this->delete->execute($affiliateId);
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

    private function normalizeCurrency(mixed $value, string $fallback): string
    {
        if (! is_string($value) || mb_trim($value) === '') {
            return $fallback;
        }

        return mb_strtoupper(mb_trim($value));
    }

    private function referenceCurrency(Affiliate $affiliate): string
    {
        if (is_string($affiliate->currency) && mb_trim($affiliate->currency) !== '') {
            return mb_strtoupper(mb_trim($affiliate->currency));
        }

        return mb_strtoupper((string) config('affiliates.currency.default', 'MYR'));
    }
}
