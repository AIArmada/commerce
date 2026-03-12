<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class AffiliateReportService
{
    /**
     * @return array{attributions: int, conversions: int, revenue_minor: int, commission_minor: int}
     */
    public function getSummary(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $conversions = AffiliateConversion::query()
            ->forOwner()
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->get(['commission_minor', 'value_minor', 'total_minor']);

        $attributions = (int) $this->applyAttributionWindow(
            AffiliateAttribution::query()->forOwner(),
            $startDate,
            $endDate,
        )->count();

        return [
            'attributions' => $attributions,
            'conversions' => $conversions->count(),
            'revenue_minor' => $this->sumRevenueMinor($conversions),
            'commission_minor' => (int) $conversions->sum('commission_minor'),
        ];
    }

    /**
     * @return array<int, array{affiliate_id: string, affiliate_code: string, name: string|null, conversions: int, revenue_minor: int, commission_minor: int}>
     */
    public function getTopAffiliates(CarbonInterface $startDate, CarbonInterface $endDate, int $limit = 10): array
    {
        $rows = AffiliateConversion::query()
            ->forOwner()
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->toBase()
            ->selectRaw(sprintf(
                'affiliate_id, MAX(affiliate_code) as affiliate_code, COUNT(*) as conversions, SUM(%s) as revenue_minor, SUM(commission_minor) as commission_minor',
                $this->revenueMinorExpression(),
            ))
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
    public function getConversionTrend(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $rows = AffiliateConversion::query()
            ->forOwner()
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->toBase()
            ->selectRaw(sprintf(
                'DATE(occurred_at) as date, COUNT(*) as conversions, SUM(%s) as revenue_minor, SUM(commission_minor) as commission_minor',
                $this->revenueMinorExpression(),
            ))
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
    public function getTrafficSources(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $conversions = AffiliateConversion::query()
            ->forOwner()
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->get(['metadata']);

        return $this->aggregateUtm($conversions);
    }

    /**
     * @return array<int, array{subject_type: string, subject_identifier: string, subject_title_snapshot: string|null, visits: int, attributions: int, conversions: int, revenue_minor: int, commission_minor: int}>
     */
    public function getTopSubjects(CarbonInterface $startDate, CarbonInterface $endDate, int $limit = 10): array
    {
        $subjects = [];

        $visitRows = AffiliateTouchpoint::query()
            ->forOwner()
            ->whereBetween('touched_at', [$startDate, $endDate])
            ->whereNotNull('subject_type')
            ->whereNotNull('subject_identifier')
            ->toBase()
            ->selectRaw('subject_type, subject_identifier, MAX(subject_title_snapshot) as subject_title_snapshot, COUNT(*) as visits')
            ->groupBy('subject_type', 'subject_identifier')
            ->get();

        foreach ($visitRows as $row) {
            $key = $this->subjectKey((string) $row->subject_type, (string) $row->subject_identifier);

            $subjects[$key] = [
                'subject_type' => (string) $row->subject_type,
                'subject_identifier' => (string) $row->subject_identifier,
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
            ->whereNotNull('subject_identifier')
            ->toBase()
            ->selectRaw('subject_type, subject_identifier, MAX(subject_title_snapshot) as subject_title_snapshot, COUNT(*) as attributions')
            ->groupBy('subject_type', 'subject_identifier')
            ->get();

        foreach ($attributionRows as $row) {
            $key = $this->subjectKey((string) $row->subject_type, (string) $row->subject_identifier);

            $subjects[$key] ??= [
                'subject_type' => (string) $row->subject_type,
                'subject_identifier' => (string) $row->subject_identifier,
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
            ->whereNotNull('subject_identifier')
            ->toBase()
            ->selectRaw(sprintf(
                'subject_type, subject_identifier, MAX(subject_title_snapshot) as subject_title_snapshot, COUNT(*) as conversions, SUM(%s) as revenue_minor, SUM(commission_minor) as commission_minor',
                $this->revenueMinorExpression(),
            ))
            ->groupBy('subject_type', 'subject_identifier')
            ->get();

        foreach ($conversionRows as $row) {
            $key = $this->subjectKey((string) $row->subject_type, (string) $row->subject_identifier);

            $subjects[$key] ??= [
                'subject_type' => (string) $row->subject_type,
                'subject_identifier' => (string) $row->subject_identifier,
                'subject_title_snapshot' => $this->nullableString($row->subject_title_snapshot),
                'visits' => 0,
                'attributions' => 0,
                'conversions' => 0,
                'revenue_minor' => 0,
                'commission_minor' => 0,
            ];

            $subjects[$key]['subject_title_snapshot'] ??= $this->nullableString($row->subject_title_snapshot);
            $subjects[$key]['conversions'] = (int) $row->conversions;
            $subjects[$key]['revenue_minor'] = (int) $row->revenue_minor;
            $subjects[$key]['commission_minor'] = (int) $row->commission_minor;
        }

        $rows = array_values($subjects);

        usort($rows, function (array $left, array $right): int {
            return [$right['conversions'], $right['revenue_minor'], $right['visits'], $right['attributions']]
                <=> [$left['conversions'], $left['revenue_minor'], $left['visits'], $left['attributions']];
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

        $conversions = AffiliateConversion::query()
            ->forOwner()
            ->where('affiliate_id', $affiliateId)
            ->get(['commission_minor', 'value_minor', 'total_minor', 'metadata']);

        $totalCommission = (int) $conversions->sum('commission_minor');
        $totalRevenue = $this->sumRevenueMinor($conversions);
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

    private function sumRevenueMinor(Collection $conversions): int
    {
        return (int) $conversions->sum(fn (AffiliateConversion $conversion): int => $this->resolveRevenueMinor($conversion));
    }

    private function resolveRevenueMinor(AffiliateConversion $conversion): int
    {
        $valueMinor = (int) $conversion->getRawOriginal('value_minor');

        if ($valueMinor !== 0) {
            return $valueMinor;
        }

        return (int) $conversion->getRawOriginal('total_minor');
    }

    private function revenueMinorExpression(): string
    {
        return 'COALESCE(NULLIF(value_minor, 0), total_minor, 0)';
    }

    private function subjectKey(string $subjectType, string $subjectIdentifier): string
    {
        return $subjectType . '|' . $subjectIdentifier;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
