<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Reporting\Actions;

use AIArmada\Affiliates\Features\Attribution\Api\AttributionReportingReader;
use AIArmada\Affiliates\Features\Attribution\Api\TouchpointReader;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionStatisticsReader;
use AIArmada\Affiliates\Features\Reporting\Api\DailyStatRecord;
use AIArmada\Affiliates\Features\Reporting\Api\DailyStatsWriter;
use AIArmada\Affiliates\Features\Reporting\Models\AffiliateDailyStat;
use AIArmada\Affiliates\Models\Affiliate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use LogicException;

final readonly class AggregateDailyStats implements DailyStatsWriter
{
    public function __construct(private DeleteAffiliateDailyStats $delete, private TouchpointReader $touchpoints, private AttributionReportingReader $attributions, private ConversionStatisticsReader $conversions) {}

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
        $connection = (new AffiliateDailyStat)->getConnection();

        if ($connection !== (new Affiliate)->getConnection()) {
            throw new LogicException('Daily affiliate aggregation requires a shared database connection.');
        }

        return $connection->transaction(function () use ($affiliateId, $date): array {
            $affiliate = Affiliate::query()->whereKey($affiliateId)->lockForUpdate()->firstOrFail();
            $dayStart = $date->startOfDay();
            $dayEnd = $date->endOfDay();
            $reference = $this->referenceCurrency($affiliate);

            $touchpointStats = $this->touchpoints->clickStats($affiliateId, $dayStart, $dayEnd);
            $clicks = $touchpointStats['clicks'];
            $uniqueClicks = $touchpointStats['unique_clicks'];

            $attributions = $this->attributions->countFirstSeen($affiliateId, $dayStart, $dayEnd);

            $legs = [];

            foreach ($this->conversions->totals($affiliateId, $reference, spl_object_id($affiliate->getConnection()), $dayStart, $dayStart->addDay()) as $row) {
                $legs[$row->currency] = [
                    'conversions' => $row->count,
                    'revenue' => $row->revenueMinor,
                    'commission' => $row->commissionMinor,
                ];
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
                    (string) $stat->getKey(),
                    $stat->currency,
                    $stat->clicks,
                    $stat->unique_clicks,
                    $stat->attributions,
                    $stat->conversions,
                    $stat->revenue_cents,
                    $stat->commission_cents,
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
        return $this->touchpoints->breakdown((string) $affiliate->getKey(), $dayStart, $dayEnd);
    }

    private function referenceCurrency(Affiliate $affiliate): string
    {
        if (is_string($affiliate->currency) && mb_trim($affiliate->currency) !== '') {
            return mb_strtoupper(mb_trim($affiliate->currency));
        }

        return mb_strtoupper((string) config('affiliates.currency.default', 'MYR'));
    }
}
