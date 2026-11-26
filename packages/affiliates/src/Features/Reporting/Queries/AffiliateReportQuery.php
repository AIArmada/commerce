<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Reporting\Queries;

use AIArmada\Affiliates\Features\Attribution\Api\AttributionReportingReader;
use AIArmada\Affiliates\Features\Attribution\Api\TouchpointReader;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionReportingReader;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionReportRow;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateDirectory;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateReportingReader;
use AIArmada\Affiliates\Features\Reporting\Api\ReportingApi;
use AIArmada\CommerceSupport\Support\CurrencyConverter;
use DateTimeInterface;
use Illuminate\Support\Collection;

final readonly class AffiliateReportQuery implements ReportingApi
{
    public function __construct(private CurrencyConverter $converter, private TouchpointReader $touchpoints, private AttributionReportingReader $attributions, private ConversionReportingReader $conversions) {}

    /**
     * Money legs are grouped by the conversion currency. Single-currency
     * results pass raw sums through untouched; mixed-currency totals are
     * converted to the default currency for display, or null when a rate
     * is missing so callers render the per-currency breakdown instead.
     *
     * @return array{attributions: int, conversions: int, revenue_minor: int|null, commission_minor: int|null, currency: string, converted: bool, by_currency: array<string, array{conversions: int, revenue_minor: int, commission_minor: int}>}
     */
    public function getSummary(DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $rows = collect($this->conversions->money($startDate, $endDate));

        $attributions = $this->attributions->countWindow($startDate, $endDate);

        $totals = $this->summarizeMoney($rows, $endDate);

        return [
            'attributions' => $attributions,
            ...$totals,
        ];
    }

    /**
     * @return array<int, array{affiliate_id: string, affiliate_code: string, name: string|null, currency: string, conversions: int, revenue_minor: int, commission_minor: int}>
     */
    public function getTopAffiliates(DateTimeInterface $startDate, DateTimeInterface $endDate, int $limit = 10): array
    {
        $rows = collect($this->conversions->byAffiliate($startDate, $endDate));

        $affiliateNamesById = app(AffiliateReportingReader::class)->names($rows->map(static fn (ConversionReportRow $row): string => (string) $row->affiliateId)->values()->all());

        $default = $this->defaultCurrency();

        // Rank on converted commission so legs in different currencies
        // compare fairly; legs without a rate sink below converted ones.
        return $rows
            ->map(fn (ConversionReportRow $row): array => [
                'affiliate_id' => (string) $row->affiliateId,
                'affiliate_code' => (string) $row->affiliateCode,
                'name' => $affiliateNamesById[(string) $row->affiliateId] ?? null,
                'currency' => $this->normalizeCurrency($row->currency ?? null),
                'conversions' => (int) $row->conversions,
                'revenue_minor' => (int) $row->revenueMinor,
                'commission_minor' => (int) $row->commissionMinor,
            ])
            ->sort(function (array $left, array $right) use ($default, $endDate): int {
                $leftRank = $this->converter->convertMinor($left['commission_minor'], $left['currency'], $default, $endDate);
                $rightRank = $this->converter->convertMinor($right['commission_minor'], $right['currency'], $default, $endDate);

                if ($leftRank === null && $rightRank === null) {
                    return [$right['commission_minor'], $left['affiliate_id']]
                        <=> [$left['commission_minor'], $right['affiliate_id']];
                }

                if ($leftRank === null) {
                    return 1;
                }

                if ($rightRank === null) {
                    return -1;
                }

                return [$rightRank, $left['affiliate_id']] <=> [$leftRank, $right['affiliate_id']];
            })
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{date: string, currency: string, conversions: int, revenue_minor: int, commission_minor: int}>
     */
    public function getConversionTrend(DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $rows = collect($this->conversions->byDay($startDate, $endDate));

        return $rows
            ->map(fn (ConversionReportRow $row): array => [
                'date' => (string) $row->date,
                'currency' => $this->normalizeCurrency($row->currency ?? null),
                'conversions' => (int) $row->conversions,
                'revenue_minor' => (int) $row->revenueMinor,
                'commission_minor' => (int) $row->commissionMinor,
            ])
            ->all();
    }

    /**
     * @return array{sources: array<string, int>, campaigns: array<string, int>}
     */
    public function getTrafficSources(DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        return $this->conversions->traffic($startDate, $endDate)->toArray();
    }

    /**
     * @return array<int, array{subject_type: string, subject_key: string, subject_title_snapshot: string|null, visits: int, attributions: int, conversions: int, revenue_minor: int|null, commission_minor: int|null, currency: string, converted: bool, by_currency: array<string, array{conversions: int, revenue_minor: int, commission_minor: int}>}>
     */
    public function getTopSubjects(DateTimeInterface $startDate, DateTimeInterface $endDate, int $limit = 10): array
    {
        $subjects = [];
        $moneyBySubject = [];

        $visitRows = $this->touchpoints->subjectVisits($startDate, $endDate);

        foreach ($visitRows as $row) {
            $key = $this->subjectKey((string) $row['subject_type'], (string) $row['subject_key']);

            $subjects[$key] = [
                'subject_type' => (string) $row['subject_type'],
                'subject_key' => (string) $row['subject_key'],
                'subject_title_snapshot' => $this->nullableString($row['subject_title_snapshot']),
                'visits' => (int) $row['visits'],
                'attributions' => 0,
                'conversions' => 0,
                'revenue_minor' => 0,
                'commission_minor' => 0,
            ];
        }

        $attributionRows = $this->attributions->subjectCounts($startDate, $endDate);

        foreach ($attributionRows as $row) {
            $key = $this->subjectKey((string) $row['subject_type'], (string) $row['subject_key']);

            $subjects[$key] ??= [
                'subject_type' => (string) $row['subject_type'],
                'subject_key' => (string) $row['subject_key'],
                'subject_title_snapshot' => $this->nullableString($row['subject_title_snapshot']),
                'visits' => 0,
                'attributions' => 0,
                'conversions' => 0,
                'revenue_minor' => 0,
                'commission_minor' => 0,
            ];

            $subjects[$key]['subject_title_snapshot'] ??= $this->nullableString($row['subject_title_snapshot']);
            $subjects[$key]['attributions'] = (int) $row['attributions'];
        }

        $conversionRows = $this->conversions->bySubject($startDate, $endDate);

        foreach ($conversionRows as $row) {
            $key = $this->subjectKey((string) $row->subjectType, (string) $row->subjectKey);

            $subjects[$key] ??= [
                'subject_type' => (string) $row->subjectType,
                'subject_key' => (string) $row->subjectKey,
                'subject_title_snapshot' => $this->nullableString($row->subjectTitleSnapshot),
                'visits' => 0,
                'attributions' => 0,
                'conversions' => 0,
                'revenue_minor' => 0,
                'commission_minor' => 0,
            ];

            $subjects[$key]['subject_title_snapshot'] ??= $this->nullableString($row->subjectTitleSnapshot);
            $moneyBySubject[$key][] = $row;
        }

        foreach ($moneyBySubject as $key => $moneyRows) {
            $totals = $this->summarizeMoney(collect($moneyRows), $endDate);

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
            $leftKey = $left['subject_type'] . '|' . ($left['subject_key'] ?? '');
            $rightKey = $right['subject_type'] . '|' . ($right['subject_key'] ?? '');

            return [$right['conversions'], $right['revenue_minor'] ?? -1, $right['visits'], $right['attributions'], $leftKey]
                <=> [$left['conversions'], $left['revenue_minor'] ?? -1, $left['visits'], $left['attributions'], $rightKey];
        });

        return array_slice($rows, 0, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    public function affiliateSummary(string $affiliateId): array
    {
        $affiliate = app(AffiliateDirectory::class)->findById($affiliateId);

        if (! $affiliate) {
            return [];
        }

        $moneyRows = collect($this->conversions->money(affiliateId: $affiliateId));

        $money = $this->summarizeMoney($moneyRows);

        $totalCommission = $money['commission_minor'];
        $totalRevenue = $money['revenue_minor'];
        $conversionCount = $money['conversions'];
        $ltv = $conversionCount > 0 && $totalRevenue !== null ? ($totalRevenue / $conversionCount) : null;

        $utm = $this->conversions->traffic(affiliateId: $affiliateId)->toArray();

        $attributionCount = $this->attributions->count($affiliateId);

        $funnel = [
            'attributions' => $attributionCount,
            'conversions' => $conversionCount,
            'conversion_rate' => $conversionCount > 0 && $attributionCount > 0
                ? round(($conversionCount / $attributionCount) * 100, 2)
                : 0,
        ];

        return [
            'affiliate' => [
                'id' => $affiliate->id,
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

    /**
     * Fold per-currency aggregate rows into display totals.
     *
     * @param  Collection<int, ConversionReportRow>  $rows
     * @return array{conversions: int, revenue_minor: int|null, commission_minor: int|null, currency: string, converted: bool, by_currency: array<string, array{conversions: int, revenue_minor: int, commission_minor: int}>}
     */
    private function summarizeMoney(Collection $rows, ?DateTimeInterface $asOf = null): array
    {
        $byCurrency = [];
        $conversions = 0;

        foreach ($rows as $row) {
            $currency = $this->normalizeCurrency($row->currency ?? null);

            $byCurrency[$currency] ??= ['conversions' => 0, 'revenue_minor' => 0, 'commission_minor' => 0];
            $byCurrency[$currency]['conversions'] += (int) $row->conversions;
            $byCurrency[$currency]['revenue_minor'] += (int) $row->revenueMinor;
            $byCurrency[$currency]['commission_minor'] += (int) $row->commissionMinor;
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
                'conversion' => null,
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

        $revenue = $this->converter->totalMinor($revenueByCurrency, $default, $asOf);
        $commission = $this->converter->totalMinor($commissionByCurrency, $default, $asOf);
        $converted = $revenue !== null && $commission !== null && $byCurrency !== [];

        return [
            'conversions' => $conversions,
            'revenue_minor' => $revenue,
            'commission_minor' => $commission,
            'currency' => $default,
            'converted' => $converted,
            'conversion' => $converted ? $this->converter->conversionDisclosure($default, $asOf) : null,
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
