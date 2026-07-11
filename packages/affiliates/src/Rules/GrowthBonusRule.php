<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Rules;

use AIArmada\Affiliates\Contracts\PerformanceBonusRule;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class GrowthBonusRule implements PerformanceBonusRule
{
    public function bonusType(): string
    {
        return 'growth';
    }

    public function isEnabled(): bool
    {
        return (bool) (config('affiliates.bonuses.growth.enabled', true));
    }

    public function calculate(CarbonImmutable $from, CarbonImmutable $to, bool $includeGlobal = false): array
    {
        $config = config('affiliates.bonuses.growth', []);

        if (! $this->isEnabled()) {
            return [];
        }

        $minimumGrowthPercentage = (float) ($config['min_growth_percentage'] ?? 50);
        $bonuses = [];

        $prevFrom = $from->subMonth()->startOfMonth();
        $prevTo = $from->subMonth()->endOfMonth();

        $affiliates = Affiliate::where('status', Active::class)->get();

        foreach ($affiliates as $affiliate) {
            $currentRevenue = $affiliate->conversions()
                ->whereBetween('occurred_at', [$from, $to])
                ->where('status', ApprovedConversion::value())
                ->sum(DB::raw('COALESCE(value_minor, 0)'));

            $previousRevenue = $affiliate->conversions()
                ->whereBetween('occurred_at', [$prevFrom, $prevTo])
                ->where('status', ApprovedConversion::value())
                ->sum(DB::raw('COALESCE(value_minor, 0)'));

            if ($previousRevenue < ($config['min_previous_revenue'] ?? 50000)) {
                continue;
            }

            $growthPercentage = (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;

            if ($growthPercentage >= $minimumGrowthPercentage) {
                $bonuses[] = [
                    'bonus_type' => $this->bonusType(),
                    'affiliate_id' => $affiliate->id,
                    'affiliate_name' => $affiliate->name,
                    'amount_minor' => $config['bonus_amount'] ?? 7500,
                    'reason' => 'Growth Bonus - ' . round($growthPercentage, 1) . '% growth vs previous month',
                    'metrics' => [
                        'current_revenue' => $currentRevenue,
                        'previous_revenue' => $previousRevenue,
                        'growth_percentage' => round($growthPercentage, 2),
                        'period' => $from->format('Y-m'),
                    ],
                ];
            }
        }

        return $bonuses;
    }
}
