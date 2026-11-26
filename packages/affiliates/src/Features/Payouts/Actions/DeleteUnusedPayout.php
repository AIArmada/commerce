<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionIntegrity;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutAccountingReader;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutRemoval;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutHold;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Features\Payouts\States\PendingPayout;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class DeleteUnusedPayout implements PayoutRemoval
{
    public function __construct(private PayoutConversionIntegrity $conversions, private PayoutAccountingReader $history, private AffiliateAccessGuard $affiliates) {}

    public function deleteUnused(string $payoutId): bool
    {
        $connection = (new AffiliatePayout)->getConnection();

        if ($connection !== (new AffiliatePayoutEvent)->getConnection()
            || $connection !== (new AffiliatePayoutOperation)->getConnection()) {
            throw new LogicException('Payout removal requires a shared database connection.');
        }

        return $connection->transaction(function () use ($payoutId): bool {
            $payout = AffiliatePayout::query()->forOwner()->lockForUpdate()->findOrFail($payoutId);

            if (! $payout->status->equals(PendingPayout::class)
                || (int) $payout->total_minor !== 0
                || (int) $payout->conversion_count !== 0
                || $payout->external_reference !== null
                || $payout->affiliate_payout_operation_id !== null
                || AffiliatePayoutOperation::query()->where('affiliate_payout_id', $payoutId)->exists()
                || $this->conversions->hasConversions($payoutId, spl_object_id($payout->getConnection()))) {
                throw ValidationException::withMessages(['payout' => 'A payout with financial or processing history cannot be deleted.']);
            }

            AffiliatePayoutEvent::query()->where('affiliate_payout_id', $payoutId)->delete();

            if (! $payout->delete()) {
                throw new LogicException('The unused payout could not be deleted.');
            }

            return true;
        }, attempts: 3);
    }

    public function removeUnusedConfiguration(string $affiliateId): void
    {
        $connection = (new AffiliatePayout)->getConnection();

        if ($connection !== (new AffiliatePayoutOperation)->getConnection()
            || $connection !== (new AffiliatePayoutMethod)->getConnection()
            || $connection !== (new AffiliatePayoutHold)->getConnection()) {
            throw new LogicException('Affiliate payout cleanup requires a shared database connection.');
        }

        $connection->transaction(function () use ($connection, $affiliateId): void {
            $this->affiliates->lockForWrite($affiliateId, spl_object_id($connection));

            if ($this->history->hasAffiliateHistory($affiliateId, spl_object_id($connection))) {
                throw ValidationException::withMessages(['affiliate' => 'An affiliate with payout history cannot be deleted.']);
            }

            AffiliatePayoutMethod::query()->where('affiliate_id', $affiliateId)->delete();
            AffiliatePayoutHold::query()->where('affiliate_id', $affiliateId)->delete();
        }, attempts: 3);
    }
}
