<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Actions\Conversions\MatureConversion;
use AIArmada\Affiliates\Actions\Conversions\ProcessConversionMaturity;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Carbon\CarbonInterface;

final class CommissionMaturityService
{
    private int $maturityDays;

    public function __construct(
        private readonly ProcessConversionMaturity $processMaturityAction,
        private readonly MatureConversion $matureConversionAction,
    ) {
        $this->maturityDays = config('affiliates.payouts.maturity_days', 30);
    }

    public function processMaturity(): int
    {
        return $this->processMaturityAction->handle();
    }

    public function matureConversion(AffiliateConversion $conversion): bool
    {
        return $this->matureConversionAction->handle($conversion);
    }

    public function getMaturityDate(AffiliateConversion $conversion): CarbonInterface
    {
        return $conversion->occurred_at->addDays($this->maturityDays);
    }

    public function isMature(AffiliateConversion $conversion): bool
    {
        return $this->getMaturityDate($conversion)->isPast();
    }

    public function getPendingMaturity(Affiliate $affiliate): int
    {
        return (int) $affiliate->conversions()
            ->where('status', 'qualified')
            ->sum('commission_minor');
    }

    public function getMaturingWithin(Affiliate $affiliate, int $days): array
    {
        $cutoffDate = now()->subDays($this->maturityDays - $days);

        return $affiliate->conversions()
            ->where('status', 'qualified')
            ->where('occurred_at', '>=', $cutoffDate)
            ->get()
            ->map(fn (AffiliateConversion $c) => [
                'id' => $c->id,
                'commission_minor' => $c->commission_minor,
                'occurred_at' => $c->occurred_at->toIso8601String(),
                'matures_at' => $this->getMaturityDate($c)->toIso8601String(),
                'days_remaining' => max(0, now()->diffInDays($this->getMaturityDate($c), false)),
            ])
            ->all();
    }
}
