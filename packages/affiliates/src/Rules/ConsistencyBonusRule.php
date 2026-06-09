<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Rules;

use AIArmada\Affiliates\Contracts\PerformanceBonusRule;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use Carbon\CarbonImmutable;

final class ConsistencyBonusRule implements PerformanceBonusRule
{
    public function bonusType(): string
    {
        return 'consistency';
    }

    public function isEnabled(): bool
    {
        return (bool) (config('affiliates.bonuses.consistency.enabled', true));
    }

    public function calculate(CarbonImmutable $from, CarbonImmutable $to, bool $includeGlobal = false): array
    {
        $config = config('affiliates.bonuses.consistency', []);

        if (! $this->isEnabled()) {
            return [];
        }

        $bonuses = [];
        $minWeeks = $config['min_weeks'] ?? 4;
        $minConversionsPerWeek = $config['min_conversions_per_week'] ?? 1;

        $affiliates = Affiliate::where('status', Active::class)->get();

        foreach ($affiliates as $affiliate) {
            $weeksWithSales = 0;
            $currentWeek = $from->copy()->startOfWeek();

            while ($currentWeek->lte($to)) {
                $weekEnd = $currentWeek->copy()->endOfWeek();

                $conversionsThisWeek = $affiliate->conversions()
                    ->whereBetween('occurred_at', [$currentWeek, $weekEnd])
                    ->where('status', ApprovedConversion::value())
                    ->count();

                if ($conversionsThisWeek >= $minConversionsPerWeek) {
                    $weeksWithSales++;
                }

                $currentWeek->addWeek();
            }

            if ($weeksWithSales >= $minWeeks) {
                $bonuses[] = [
                    'bonus_type' => $this->bonusType(),
                    'affiliate_id' => $affiliate->id,
                    'affiliate_name' => $affiliate->name,
                    'amount_minor' => $config['bonus_amount'] ?? 5000,
                    'reason' => "Consistency Bonus - Sales in {$weeksWithSales} consecutive weeks",
                    'metrics' => [
                        'weeks_with_sales' => $weeksWithSales,
                        'period' => $from->format('Y-m'),
                    ],
                ];
            }
        }

        return $bonuses;
    }
}
