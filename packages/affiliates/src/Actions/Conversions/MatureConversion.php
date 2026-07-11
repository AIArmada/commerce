<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Conversions;

use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\QualifiedConversion;
use Lorisleiva\Actions\Concerns\AsAction;

final class MatureConversion
{
    use AsAction;

    private int $maturityDays;

    public function __construct(
        private readonly ApplyConversionAccounting $accounting,
    ) {
        $this->maturityDays = config('affiliates.payouts.maturity_days', 30);
    }

    public function handle(AffiliateConversion $conversion): bool
    {
        if (! $conversion->status->equals(QualifiedConversion::class)) {
            return false;
        }

        $maturityDate = $conversion->occurred_at->addDays($this->maturityDays);

        if ($maturityDate->isFuture()) {
            return false;
        }

        $previousStatus = $conversion->status;

        $conversion->update([
            'status' => ApprovedConversion::class,
            'metadata' => array_merge($conversion->metadata ?? [], [
                'matured_at' => now()->toIso8601String(),
            ]),
        ]);

        $this->accounting->handle($conversion, $previousStatus);

        return true;
    }
}
