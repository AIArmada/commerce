<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Conversions;

use AIArmada\Affiliates\Models\AffiliateConversion;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Process maturity for all pending commissions.
 */
final class ProcessConversionMaturity
{
    use AsAction;

    public function __construct(
        private readonly MatureConversion $matureConversion,
    ) {}

    /**
     * Process maturity for all qualified conversions that have passed their maturity date.
     */
    public function handle(): int
    {
        $maturityDays = config('affiliates.payouts.maturity_days', 30);
        $matured = 0;

        $conversions = AffiliateConversion::query()
            ->where('status', 'qualified')
            ->where('occurred_at', '<=', now()->subDays($maturityDays))
            ->with('affiliate')
            ->get();

        foreach ($conversions as $conversion) {
            if ($this->matureConversion->handle($conversion)) {
                $matured++;
            }
        }

        return $matured;
    }
}
