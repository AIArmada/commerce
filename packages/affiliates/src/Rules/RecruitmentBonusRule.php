<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Rules;

use AIArmada\Affiliates\Contracts\PerformanceBonusRule;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;

final class RecruitmentBonusRule implements PerformanceBonusRule
{
    public function bonusType(): string
    {
        return 'recruitment';
    }

    public function isEnabled(): bool
    {
        return (bool) (config('affiliates.bonuses.recruitment.enabled', true));
    }

    public function calculate(CarbonImmutable $from, CarbonImmutable $to, bool $includeGlobal = false): array
    {
        $config = config('affiliates.bonuses.recruitment', []);

        if (! $this->isEnabled()) {
            return [];
        }

        $bonuses = [];

        $recruiters = Affiliate::query()
            ->forOwner(OwnerContext::CURRENT, $includeGlobal)
            ->where('status', Active::class)
            ->whereHas('children', function ($query) use ($from, $to, $includeGlobal): void {
                $query->forOwner(OwnerContext::CURRENT, $includeGlobal)
                    ->whereBetween('created_at', [$from, $to])
                    ->where('status', Active::class);
            })
            ->withCount([
                'children' => function ($query) use ($from, $to, $includeGlobal): void {
                    $query->forOwner(OwnerContext::CURRENT, $includeGlobal)
                        ->whereBetween('created_at', [$from, $to])
                        ->where('status', Active::class);
                },
            ])
            ->having('children_count', '>=', $config['min_recruits'] ?? 3)
            ->get();

        foreach ($recruiters as $recruiter) {
            $recruitCount = $recruiter->children_count;
            $bonusAmount = min(
                $recruitCount * ($config['bonus_per_recruit'] ?? 2500),
                $config['max_bonus'] ?? 25000
            );

            $bonuses[] = [
                'bonus_type' => $this->bonusType(),
                'affiliate_id' => $recruiter->id,
                'affiliate_name' => $recruiter->name,
                'amount_minor' => $bonusAmount,
                'reason' => "Recruitment Bonus - {$recruitCount} new active recruits in " . $from->format('F Y'),
                'metrics' => [
                    'recruit_count' => $recruitCount,
                    'period' => $from->format('Y-m'),
                ],
            ];
        }

        return $bonuses;
    }
}
