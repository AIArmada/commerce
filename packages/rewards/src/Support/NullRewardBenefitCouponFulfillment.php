<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

use AIArmada\Rewards\Contracts\RewardBenefitCouponFulfillment;
use AIArmada\Rewards\Models\RewardBenefitGrant;
use AIArmada\Rewards\Models\RewardBenefitItem;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Fail-closed coupon fulfillment: coupon-type benefits throw with a clear
 * exception while non-coupon benefits are unaffected. Hosts with a
 * coupon/voucher system bind a real implementation.
 */
final class NullRewardBenefitCouponFulfillment implements RewardBenefitCouponFulfillment
{
    public function issue(RewardBenefitGrant $grant, RewardBenefitItem $item, CarbonImmutable $decided): string
    {
        throw new InvalidArgumentException('Coupon fulfillment requires a bound RewardBenefitCouponFulfillment; non-coupon benefits are unaffected.');
    }

    public function assertCanonicalUsage(RewardBenefitGrant $grant, array $useEvidence): void
    {
        throw new InvalidArgumentException('Coupon consumption requires a bound RewardBenefitCouponFulfillment; non-coupon benefits are unaffected.');
    }

    public function revoke(RewardBenefitGrant $grant): void
    {
        throw new InvalidArgumentException('Coupon revocation requires a bound RewardBenefitCouponFulfillment; non-coupon benefits are unaffected.');
    }
}
