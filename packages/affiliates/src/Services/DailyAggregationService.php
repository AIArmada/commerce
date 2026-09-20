<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateDailyStat;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use AIArmada\CommerceSupport\Support\CurrencyConverter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class DailyAggregationService
{
    public function __construct(private readonly CurrencyConverter $converter) {}

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
     *
     * One row per active currency. Click-level counts live on the affiliate
     * currency row only so period sums never double-count; conversion counts
     * and money are per leg and sum cleanly.
     *
     * @return Collection<int, AffiliateDailyStat>
     */
    public function aggregateForAffiliate(Affiliate $affiliate, CarbonImmutable $date): Collection
    {
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

        if ($legs === []) {
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

        return AffiliateDailyStat::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('date', $date->toDateString())
            ->orderBy('currency')
            ->get();
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
     * Money folds per currency and converts to the affiliate currency when
     * legs span currencies; totals are null when a rate is missing.
     *
     * @return array{clicks: int, unique_clicks: int, attributions: int, conversions: int, revenue_cents: int|null, commission_cents: int|null, currency: string, converted: bool, by_currency: array<string, array{conversions: int, revenue_cents: int, commission_cents: int}>, conversion_rate: float|null, epc_cents: float|null}
     */
    public function getAggregatedStats(Affiliate $affiliate, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = AffiliateDailyStat::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->toBase()
            ->selectRaw('currency, COALESCE(SUM(clicks), 0) as clicks, COALESCE(SUM(unique_clicks), 0) as unique_clicks, COALESCE(SUM(attributions), 0) as attributions, COALESCE(SUM(conversions), 0) as conversions, COALESCE(SUM(revenue_cents), 0) as revenue_cents, COALESCE(SUM(commission_cents), 0) as commission_cents')
            ->groupBy('currency')
            ->get();

        $reference = $this->referenceCurrency($affiliate);
        $byCurrency = [];
        $clicks = 0;
        $uniqueClicks = 0;
        $attributions = 0;
        $conversions = 0;

        foreach ($rows as $row) {
            $currency = $this->normalizeCurrency($row->currency ?? null, $reference);

            $byCurrency[$currency] ??= ['conversions' => 0, 'revenue_cents' => 0, 'commission_cents' => 0];
            $byCurrency[$currency]['conversions'] += (int) $row->conversions;
            $byCurrency[$currency]['revenue_cents'] += (int) $row->revenue_cents;
            $byCurrency[$currency]['commission_cents'] += (int) $row->commission_cents;
            $clicks += (int) $row->clicks;
            $uniqueClicks += (int) $row->unique_clicks;
            $attributions += (int) $row->attributions;
            $conversions += (int) $row->conversions;
        }

        if ($rows->isEmpty()) {
            return [
                'clicks' => 0,
                'unique_clicks' => 0,
                'attributions' => 0,
                'conversions' => 0,
                'revenue_cents' => 0,
                'commission_cents' => 0,
                'currency' => $reference,
                'converted' => false,
                'by_currency' => [],
                'conversion_rate' => null,
                'epc_cents' => null,
            ];
        }

        if (count($byCurrency) === 1) {
            $only = (string) array_key_first($byCurrency);
            $single = $byCurrency[$only];

            return [
                'clicks' => $clicks,
                'unique_clicks' => $uniqueClicks,
                'attributions' => $attributions,
                'conversions' => $conversions,
                'revenue_cents' => $single['revenue_cents'],
                'commission_cents' => $single['commission_cents'],
                'currency' => $only,
                'converted' => false,
                'by_currency' => $byCurrency,
                'conversion_rate' => $clicks > 0 ? $conversions / $clicks : 0,
                'epc_cents' => $clicks > 0 ? $single['commission_cents'] / $clicks : 0,
            ];
        }

        $revenueByCurrency = [];
        $commissionByCurrency = [];

        foreach ($byCurrency as $currency => $money) {
            $revenueByCurrency[$currency] = $money['revenue_cents'];
            $commissionByCurrency[$currency] = $money['commission_cents'];
        }

        $revenue = $this->converter->totalMinor($revenueByCurrency, $reference);
        $commission = $this->converter->totalMinor($commissionByCurrency, $reference);

        return [
            'clicks' => $clicks,
            'unique_clicks' => $uniqueClicks,
            'attributions' => $attributions,
            'conversions' => $conversions,
            'revenue_cents' => $revenue,
            'commission_cents' => $commission,
            'currency' => $reference,
            'converted' => $revenue !== null && $commission !== null,
            'by_currency' => $byCurrency,
            'conversion_rate' => $clicks > 0 ? $conversions / $clicks : 0,
            'epc_cents' => $clicks > 0 && $commission !== null ? $commission / $clicks : null,
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
