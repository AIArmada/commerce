<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Queries;

use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class PayoutOperationQuery
{
    /** @return Builder<AffiliatePayoutOperation> */
    public static function forPayout(AffiliatePayout $payout, Model | string | null $owner = OwnerContext::CURRENT): Builder
    {
        if ((new AffiliatePayoutOperation)->getConnection() !== $payout->getConnection()) {
            throw new LogicException('Payout operation requires a shared database connection.');
        }

        return AffiliatePayoutOperation::query()->forOwner($owner)->where('id', $payout->affiliate_payout_operation_id);
    }
}
