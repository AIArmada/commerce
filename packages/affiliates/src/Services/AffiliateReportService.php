<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Illuminate\Support\Collection;

final class AffiliateReportService
{
    /**
     * @return array<string, mixed>
     */
    public function affiliateSummary(string $affiliateId): array
    {
        /** @var Affiliate|null $affiliate */
        $affiliate = Affiliate::query()->find($affiliateId);

        if (! $affiliate) {
            return [];
        }

        $conversions = AffiliateConversion::query()
            ->where('affiliate_id', $affiliateId)
            ->get();

        $totalCommission = (int) $conversions->sum('commission_minor');
        $totalRevenue = (int) $conversions->sum('total_minor');
        $conversionCount = $conversions->count();
        $ltv = $conversionCount > 0 ? ($totalRevenue / $conversionCount) : 0;

        $utm = $this->aggregateUtm($conversions);
        $funnel = [
            'attributions' => (int) $affiliate->attributions()->count(),
            'conversions' => $conversionCount,
            'conversion_rate' => $conversionCount > 0 && $affiliate->attributions()->count() > 0
                ? round(($conversionCount / $affiliate->attributions()->count()) * 100, 2)
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

        /** @var AffiliateConversion $conversion */
        foreach ($conversions as $conversion) {
            $metadata = is_array($conversion->metadata) ? $conversion->metadata : [];
            $source = $metadata['source'] ?? null;
            $campaign = $metadata['campaign'] ?? null;

            if ($source && is_string($source)) {
                $sources[$source] = ($sources[$source] ?? 0) + 1;
            }

            if ($campaign && is_string($campaign)) {
                $campaigns[$campaign] = ($campaigns[$campaign] ?? 0) + 1;
            }
        }

        return [
            'sources' => $sources,
            'campaigns' => $campaigns,
        ];
    }
}
