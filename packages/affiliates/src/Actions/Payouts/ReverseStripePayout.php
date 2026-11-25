<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Payouts;

use AIArmada\Affiliates\Contracts\PayoutProcessorRegistry;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Queries\PayoutRequestQuery;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;

final readonly class ReverseStripePayout
{
    public function __construct(private PayoutProcessorRegistry $processors) {}

    public function execute(string $payoutId): bool
    {
        if ((bool) config('affiliates.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(AffiliatePayout::class, $payoutId);
        }

        $payout = AffiliatePayout::query()->with(['operation', 'payee'])->findOrFail($payoutId);

        return $payout->getConnection()->transaction(function () use ($payout): bool {
            $operation = AffiliatePayoutOperation::query()->whereKey($payout->affiliate_payout_operation_id)->lockForUpdate()->first();

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
