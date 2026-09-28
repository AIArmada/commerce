<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Conversions;

use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\RejectedConversion;
use AIArmada\Affiliates\States\ReversedConversion;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Settle a conversion as not payable, with correct money movement.
 *
 * Holding and approved conversions are rejected (holding/available
 * voided). Paid conversions route to reversal: the money left via
 * payout, so only a clawback leg recovers it. Terminal states are
 * idempotent no-ops.
 */
final class VoidAffiliateConversion
{
    use AsAction;

    public function __construct(
        private readonly ApplyConversionAccounting $accounting,
        private readonly ReverseAffiliateConversion $reverser,
    ) {}

    /**
     * @return AffiliateConversion The reversal leg when the conversion was paid, otherwise the settled original.
     */
    public function handle(AffiliateConversion $conversion, string $reason): AffiliateConversion
    {
        return DB::transaction(function () use ($conversion, $reason): AffiliateConversion {
            $locked = AffiliateConversion::query()
                ->whereKey($conversion->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->assertNotReservedByOpenPayout();

            if ($locked->status->equals(RejectedConversion::class)
                || $locked->status->equals(ReversedConversion::class)) {
                return $locked;
            }

            if ($locked->status->equals(PaidConversion::class)) {
                return $this->reverser->handle($locked, $reason);
            }

            $previousStatus = $locked->status;
            $locked->update(['status' => RejectedConversion::class]);

            $this->accounting->handle($locked, $previousStatus);

            return $locked->refresh();
        }, attempts: 3);
    }
}
