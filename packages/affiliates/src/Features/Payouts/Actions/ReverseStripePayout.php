<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Contracts\PayoutProcessorRegistry;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutReversal;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Queries\PayoutRequestQuery;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;
use LogicException;

final readonly class ReverseStripePayout implements PayoutReversal
{
    public function __construct(private PayoutProcessorRegistry $processors) {}

    public function reverse(string $payoutId): bool
    {
        if ((bool) config('affiliates.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(AffiliatePayout::class, $payoutId);
        }

        $connection = (new AffiliatePayout)->getConnection();

        if ($connection !== (new AffiliatePayoutOperation)->getConnection()) {
            throw new LogicException('Payout reversal requires a shared database connection.');
        }

        return $connection->transaction(function () use ($payoutId): bool {
            $payout = AffiliatePayout::query()->forOwner()->with('payee')->lockForUpdate()->findOrFail($payoutId);
            $operation = $payout->operation()->forOwner()->lockForUpdate()->first();

            if (! $operation instanceof AffiliatePayoutOperation) {
                return false;
            }

            $payout->setRelation('operation', $operation);

            if ($operation->status === 'reversed') {
                return true;
            }

            if (! $this->processors->make('stripe_connect')->cancel(PayoutRequestQuery::fromPayout($payout, []))) {
                return false;
            }

            $operation->forceFill(['status' => 'reversed', 'completed_at' => CarbonImmutable::now()])->save();

            return true;
        });
    }
}
