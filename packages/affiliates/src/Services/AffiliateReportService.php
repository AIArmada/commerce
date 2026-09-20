<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use AIArmada\CommerceSupport\Support\CurrencyConverter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use stdClass;

final class AffiliateReportService
{
    public function __construct(private readonly CurrencyConverter $converter) {}

    /**
     * Money legs are grouped by the conversion currency. Single-currency
     * results pass raw sums through untouched; mixed-currency totals are
     * converted to the default currency for display, or null when a rate
     * is missing so callers render the per-currency breakdown instead.
     *
     * @return array{attributions: int, conversions: int, revenue_minor: int|null, commission_minor: int|null, currency: string, converted: bool, by_currency: array<string, array{conversions: int, revenue_minor: int, commission_minor: int}>}
     */
    public function getSummary(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $rows = AffiliateConversion::query()
            ->forOwner()
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->toBase()
            ->selectRaw(sprintf(
                'commission_currency as currency, COUNT(*) as conversions, COALESCE(SUM(%s), 0) as revenue_minor, COALESCE(SUM(commission_minor), 0) as commission_minor',
                $this->revenueMinorExpression(),
            ))
            ->groupBy('commission_currency')
            ->get();

        $attributions = (int) $this->applyAttributionWindow(
            AffiliateAttribution::query()->forOwner(),
            $startDate,
            $endDate,
        )->count();

        $totals = $this->summarizeMoney($rows);

        return [
            'attributions' => $attributions,
            ...$totals,
        ];
    }

    /**
     * @return array<int, array{affiliate_id: string, affiliate_code: string, name: string|null, currency: string, conversions: int, revenue_minor: int, commission_minor: int}>
     */
    public function getTopAffiliates(CarbonInterface $startDate, CarbonInterface $endDate, int $limit = 10): array
    {
        $rows = AffiliateConversion::query()
            ->forOwner()
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->toBase()
            ->selectRaw(sprintf(
                'affiliate_id, commission_currency as currency, MAX(affiliate_code) as affiliate_code, COUNT(*) as conversions, SUM(%s) as revenue_minor, SUM(commission_minor) as commission_minor',
                $this->revenueMinorExpression(),
            ))
            ->groupBy('affiliate_id', 'commission_currency')
            ->get();

        $affiliateNamesById = Affiliate::query()
            ->forOwner()
            ->whereIn('id', $rows->pluck('affiliate_id')->all())
            ->pluck('name', 'id');

        $default = $this->defaultCurrency();

        // Rank on converted commission so legs in different currencies
        // compare fairly; legs without a rate sink below converted ones.
        return $rows
            ->map(fn (object $row): array => [
                'affiliate_id' => (string) $row->affiliate_id,
                'affiliate_code' => (string) $row->affiliate_code,
                'name' => $affiliateNamesById[(string) $row->affiliate_id] ?? null,
                'currency' => $this->normalizeCurrency($row->currency ?? null),
                'conversions' => (int) $row->conversions,
                'revenue_minor' => (int) $row->revenue_minor,
                'commission_minor' => (int) $row->commission_minor,
            ])
            ->sort(function (array $left, array $right) use ($default): int {
                $leftRank = $this->converter->convertMinor($left['commission_minor'], $left['currency'], $default);
                $rightRank = $this->converter->convertMinor($right['commission_minor'], $right['currency'], $default);

                if ($leftRank === null && $rightRank === null) {
                    return $right['commission_minor'] <=> $left['commission_minor'];
                }

                if ($leftRank === null) {
                    return 1;
                }

                if ($rightRank === null) {
                    return -1;
                }

                return $rightRank <=> $leftRank;
            })
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{date: string, currency: string, conversions: int, revenue_minor: int, commission_minor: int}>
     */
    public function getConversionTrend(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $rows = AffiliateConversion::query()
            ->forOwner()
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->toBase()
            ->selectRaw(sprintf(
                'DATE(occurred_at) as date, commission_currency as currency, COUNT(*) as conversions, SUM(%s) as revenue_minor, SUM(commission_minor) as commission_minor',
                $this->revenueMinorExpression(),
            ))
            ->groupBy('date', 'commission_currency')
            ->orderBy('date')
            ->get();

        return $rows
            ->map(fn (object $row): array => [
                'date' => (string) $row->date,
                'currency' => $this->normalizeCurrency($row->currency ?? null),
                'conversions' => (int) $row->conversions,
                'revenue_minor' => (int) $row->revenue_minor,
                'commission_minor' => (int) $row->commission_minor,
            ])
            ->all();
    }

    /**
     * @return array{sources: array<string, int>, campaigns: array<string, int>}
     */
    public function getTrafficSources(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $conversionsTable = (new AffiliateConversion)->getTable();
        $attributionsTable = (new AffiliateAttribution)->getTable();

        $rows = AffiliateConversion::query()
            ->forOwner()
            ->whereBetween("{$conversionsTable}.occurred_at", [$startDate, $endDate])
            ->join($attributionsTable, "{$attributionsTable}.id", '=', "{$conversionsTable}.affiliate_attribution_id")
            ->toBase()
            ->selectRaw("{$attributionsTable}.source as source, {$attributionsTable}.campaign as campaign, COUNT(*) as conversions")
            ->groupBy("{$attributionsTable}.source", "{$attributionsTable}.campaign")
            ->get();

        $sources = [];
        $campaigns = [];

        foreach ($rows as $row) {
            if (is_string($row->source) && $row->source !== '') {
                $sources[$row->source] = ($sources[$row->source] ?? 0) + (int) $row->conversions;
            }

            if (is_string($row->campaign) && $row->campaign !== '') {
                $campaigns[$row->campaign] = ($campaigns[$row->campaign] ?? 0) + (int) $row->conversions;
            }
        }

        return [
            'sources' => $sources,
            'campaigns' => $campaigns,
        ];
    }

    /**
     * @return array<int, array{subject_type: string, subject_key: string, subject_title_snapshot: string|null, visits: int, attributions: int, conversions: int, revenue_minor: int|null, commission_minor: int|null, currency: string, converted: bool, by_currency: array<string, array{conversions: int, revenue_minor: int, commission_minor: int}>}>
     */
    public function getTopSubjects(CarbonInterface $startDate, CarbonInterface $endDate, int $limit = 10): array
    {
        $subjects = [];
        $moneyBySubject = [];

        $visitRows = AffiliateTouchpoint::query()
            ->forOwner()
            ->whereBetween('touched_at', [$startDate, $endDate])
            ->whereNotNull('subject_type')
            ->whereNotNull('subject_key')
            ->toBase()
            ->selectRaw('subject_type, subject_key, MAX(subject_title_snapshot) as subject_title_snapshot, COUNT(*) as visits')
            ->groupBy('subject_type', 'subject_key')
            ->get();

        foreach ($visitRows as $row) {
            $key = $this->subjectKey((string) $row->subject_type, (string) $row->subject_key);

            $subjects[$key] = [
                'subject_type' => (string) $row->subject_type,
                'subject_key' => (string) $row->subject_key,
                'subject_title_snapshot' => $this->nullableString($row->subject_title_snapshot),
                'visits' => (int) $row->visits,
                'attributions' => 0,
                'conversions' => 0,
                'revenue_minor' => 0,
                'commission_minor' => 0,
            ];
        }

        $attributionRows = $this->applyAttributionWindow(
            AffiliateAttribution::query()->forOwner(),
            $startDate,
            $endDate,
        )
            ->whereNotNull('subject_type')
            ->whereNotNull('subject_key')
            ->toBase()
            ->selectRaw('subject_type, subject_key, MAX(subject_title_snapshot) as subject_title_snapshot, COUNT(*) as attributions')
            ->groupBy('subject_type', 'subject_key')
            ->get();

        foreach ($attributionRows as $row) {
            $key = $this->subjectKey((string) $row->subject_type, (string) $row->subject_key);

            $subjects[$key] ??= [
                'subject_type' => (string) $row->subject_type,
                'subject_key' => (string) $row->subject_key,
                'subject_title_snapshot' => $this->nullableString($row->subject_title_snapshot),
                'visits' => 0,
                'attributions' => 0,
                'conversions' => 0,
                'revenue_minor' => 0,
                'commission_minor' => 0,
            ];

            $subjects[$key]['subject_title_snapshot'] ??= $this->nullableString($row->subject_title_snapshot);
            $subjects[$key]['attributions'] = (int) $row->attributions;
        }

        $conversionRows = AffiliateConversion::query()
            ->forOwner()
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->whereNotNull('subject_type')
            ->whereNotNull('subject_key')
            ->toBase()
            ->selectRaw(sprintf(
                'subject_type, subject_key, MAX(subject_title_snapshot) as subject_title_snapshot, commission_currency as currency, COUNT(*) as conversions, SUM(%s) as revenue_minor, SUM(commission_minor) as commission_minor',
                $this->revenueMinorExpression(),
            ))
            ->groupBy('subject_type', 'subject_key', 'commission_currency')
            ->get();

        foreach ($conversionRows as $row) {
            $key = $this->subjectKey((string) $row->subject_type, (string) $row->subject_key);

            $subjects[$key] ??= [
                'subject_type' => (string) $row->subject_type,
                'subject_key' => (string) $row->subject_key,
                'subject_title_snapshot' => $this->nullableString($row->subject_title_snapshot),
                'visits' => 0,
                'attributions' => 0,
                'conversions' => 0,
                'revenue_minor' => 0,
                'commission_minor' => 0,
            ];

            $subjects[$key]['subject_title_snapshot'] ??= $this->nullableString($row->subject_title_snapshot);
            $moneyBySubject[$key][] = $row;
        }

        foreach ($moneyBySubject as $key => $moneyRows) {
            $totals = $this->summarizeMoney(collect($moneyRows));

            $subjects[$key]['conversions'] = $totals['conversions'];
            $subjects[$key]['revenue_minor'] = $totals['revenue_minor'];
            $subjects[$key]['commission_minor'] = $totals['commission_minor'];
            $subjects[$key]['currency'] = $totals['currency'];
            $subjects[$key]['converted'] = $totals['converted'];
            $subjects[$key]['by_currency'] = $totals['by_currency'];
        }

        foreach ($subjects as $key => $subject) {
            $subjects[$key]['currency'] ??= $this->defaultCurrency();
            $subjects[$key]['converted'] ??= false;
            $subjects[$key]['by_currency'] ??= [];
        }

        $rows = array_values($subjects);

        usort($rows, function (array $left, array $right): int {
            return [$right['conversions'], $right['revenue_minor'] ?? -1, $right['visits'], $right['attributions']]
                <=> [$left['conversions'], $left['revenue_minor'] ?? -1, $left['visits'], $left['attributions']];
        });

        return array_slice($rows, 0, $limit);
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

        $conversionsTable = (new AffiliateConversion)->getTable();
        $attributionsTable = (new AffiliateAttribution)->getTable();

        $moneyRows = AffiliateConversion::query()
            ->forOwner()
            ->where('affiliate_id', $affiliateId)
            ->toBase()
            ->selectRaw(sprintf(
                'commission_currency as currency, COUNT(*) as conversions, COALESCE(SUM(%s), 0) as revenue_minor, COALESCE(SUM(commission_minor), 0) as commission_minor',
                $this->revenueMinorExpression(),
            ))
            ->groupBy('commission_currency')
            ->get();

        $money = $this->summarizeMoney($moneyRows);

        $totalCommission = $money['commission_minor'];
        $totalRevenue = $money['revenue_minor'];
        $conversionCount = $money['conversions'];
        $ltv = $conversionCount > 0 && $totalRevenue !== null ? ($totalRevenue / $conversionCount) : null;

        $utmRows = AffiliateConversion::query()
            ->forOwner()
            ->where("{$conversionsTable}.affiliate_id", $affiliateId)
            ->join($attributionsTable, "{$attributionsTable}.id", '=', "{$conversionsTable}.affiliate_attribution_id")
            ->toBase()
            ->selectRaw("{$attributionsTable}.source as source, {$attributionsTable}.campaign as campaign, COUNT(*) as conversions")
            ->groupBy("{$attributionsTable}.source", "{$attributionsTable}.campaign")
            ->get();

        $utm = ['sources' => [], 'campaigns' => []];

        foreach ($utmRows as $row) {
            if (is_string($row->source) && $row->source !== '') {
                $utm['sources'][$row->source] = ($utm['sources'][$row->source] ?? 0) + (int) $row->conversions;
            }

            if (is_string($row->campaign) && $row->campaign !== '') {
                $utm['campaigns'][$row->campaign] = ($utm['campaigns'][$row->campaign] ?? 0) + (int) $row->conversions;
            }
        }

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
                'ltv_minor' => $ltv === null ? null : (int) $ltv,
                'currency' => $money['currency'],
                'converted' => $money['converted'],
                'by_currency' => $money['by_currency'],
            ],
            'funnel' => $funnel,
            'utm' => $utm,
        ];
    }

    private function applyAttributionWindow(Builder $query, CarbonInterface $startDate, CarbonInterface $endDate): Builder
    {
        return $query->where(function (Builder $builder) use ($startDate, $endDate): void {
            $builder
                ->whereBetween('first_seen_at', [$startDate, $endDate])
                ->orWhere(function (Builder $fallback) use ($startDate, $endDate): void {
                    $fallback
                        ->whereNull('first_seen_at')
                        ->whereBetween('created_at', [$startDate, $endDate]);
                });
        });
    }

    private function revenueMinorExpression(): string
    {
        return 'COALESCE(value_minor, 0)';
    }

    /**
     * Fold per-currency aggregate rows into display totals.
     *
     * @param  Collection<int, stdClass>  $rows
     * @return array{conversions: int, revenue_minor: int|null, commission_minor: int|null, currency: string, converted: bool, by_currency: array<string, array{conversions: int, revenue_minor: int, commission_minor: int}>}
     */
    private function summarizeMoney(Collection $rows): array
    {
        $byCurrency = [];
        $conversions = 0;

        foreach ($rows as $row) {
            $currency = $this->normalizeCurrency($row->currency ?? null);

            $byCurrency[$currency] ??= ['conversions' => 0, 'revenue_minor' => 0, 'commission_minor' => 0];
            $byCurrency[$currency]['conversions'] += (int) $row->conversions;
            $byCurrency[$currency]['revenue_minor'] += (int) $row->revenue_minor;
            $byCurrency[$currency]['commission_minor'] += (int) $row->commission_minor;
            $conversions += (int) $row->conversions;
        }

        if (count($byCurrency) === 1) {
            $only = (string) array_key_first($byCurrency);
            $single = $byCurrency[$only] ?? ['conversions' => 0, 'revenue_minor' => 0, 'commission_minor' => 0];

            return [
                'conversions' => $conversions,
                'revenue_minor' => $single['revenue_minor'],
                'commission_minor' => $single['commission_minor'],
                'currency' => $only,
                'converted' => false,
                'by_currency' => $byCurrency,
            ];
        }

        $default = $this->defaultCurrency();
        $revenueByCurrency = [];
        $commissionByCurrency = [];

        foreach ($byCurrency as $currency => $money) {
            $revenueByCurrency[$currency] = $money['revenue_minor'];
            $commissionByCurrency[$currency] = $money['commission_minor'];
        }

        $revenue = $this->converter->totalMinor($revenueByCurrency, $default);
        $commission = $this->converter->totalMinor($commissionByCurrency, $default);

        return [
            'conversions' => $conversions,
            'revenue_minor' => $revenue,
            'commission_minor' => $commission,
            'currency' => $default,
            'converted' => $revenue !== null && $commission !== null && $byCurrency !== [],
            'by_currency' => $byCurrency,
        ];
    }

    private function normalizeCurrency(mixed $value): string
    {
        if (! is_string($value) || mb_trim($value) === '') {
            return $this->defaultCurrency();
        }

        return mb_strtoupper(mb_trim($value));
    }

    private function defaultCurrency(): string
    {
        return mb_strtoupper((string) config('affiliates.currency.default', 'MYR'));
    }

    private function subjectKey(string $subjectType, string $subjectKey): string
    {
        return $subjectType . '|' . $subjectKey;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
