<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionSettlement;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutRelease;
use AIArmada\Affiliates\Features\Payouts\Queries\PayoutOperationQuery;
use AIArmada\Affiliates\Models\AffiliatePayout;
use Carbon\CarbonImmutable;

final readonly class ReleasePayoutFunds implements PayoutRelease
{
    public function __construct(private PayoutConversionSettlement $settlement) {}

    public function release(string $payoutId): void
    {
        (new AffiliatePayout)->getConnection()->transaction(function () use ($payoutId): void {
            $payout = AffiliatePayout::query()->forOwner()->lockForUpdate()->findOrFail($payoutId);

            if ($this->settlement->release($payoutId)) {
                $operation = PayoutOperationQuery::forPayout($payout)->lockForUpdate()->firstOrFail();
                $operation->forceFill(['funds_released_at' => CarbonImmutable::now()])->save();
            }
        }, attempts: 3);
    }
}
