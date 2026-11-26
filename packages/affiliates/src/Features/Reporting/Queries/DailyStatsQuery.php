<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Reporting\Queries;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;
use AIArmada\Affiliates\Features\Reporting\Api\DailyStatsReader;
use AIArmada\Affiliates\Features\Reporting\Models\AffiliateDailyStat;
use AIArmada\CommerceSupport\Support\CurrencyConverter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class DailyStatsQuery implements DailyStatsReader
{
    public function __construct(private CurrencyConverter $converter) {}

    /**
     * Get aggregated stats for an affiliate over a period.
     *
     * Money folds per currency and converts to the affiliate currency when
     * legs span currencies; totals are null when a rate is missing.
     *
     * @return array{clicks: int, unique_clicks: int, attributions: int, conversions: int, revenue_cents: int|null, commission_cents: int|null, currency: string, converted: bool, by_currency: array<string, array{conversions: int, revenue_cents: int, commission_cents: int}>, conversion_rate: float|null, epc_cents: float|null}
     */
    public function getAggregatedStats(string $affiliateId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $affiliate = app(AffiliateProfileReader::class)->find($affiliateId, spl_object_id((new AffiliateDailyStat)->getConnection()), includeGlobal: true)
            ?? throw new ModelNotFoundException('Affiliate not found.');
        $query = AffiliateDailyStat::query()->where('affiliate_id', $affiliate->reference->id);

        if (config('affiliates.owner.enabled', false)) {
            $query->where('owner_type', $affiliate->reference->ownerType)->where('owner_id', $affiliate->reference->ownerId);
        }

        $rows = $query
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->toBase()
            ->selectRaw('currency, COALESCE(SUM(clicks), 0) as clicks, COALESCE(SUM(unique_clicks), 0) as unique_clicks, COALESCE(SUM(attributions), 0) as attributions, COALESCE(SUM(conversions), 0) as conversions, COALESCE(SUM(revenue_cents), 0) as revenue_cents, COALESCE(SUM(commission_cents), 0) as commission_cents')
            ->groupBy('currency')
            ->get();

        $reference = $this->referenceCurrency($affiliate->currency);
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

        $revenue = $this->converter->totalMinor($revenueByCurrency, $reference, $to);
        $commission = $this->converter->totalMinor($commissionByCurrency, $reference, $to);

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

    private function normalizeCurrency(mixed $value, string $fallback): string
    {
        if (! is_string($value) || mb_trim($value) === '') {
            return $fallback;
        }

        return mb_strtoupper(mb_trim($value));
    }

    private function referenceCurrency(?string $currency): string
    {
        if (is_string($currency) && mb_trim($currency) !== '') {
            return mb_strtoupper(mb_trim($currency));
        }

        return mb_strtoupper((string) config('affiliates.currency.default', 'MYR'));
    }
}
