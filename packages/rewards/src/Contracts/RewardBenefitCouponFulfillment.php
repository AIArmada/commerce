<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Contracts;

use AIArmada\Rewards\Models\RewardBenefitGrant;
use AIArmada\Rewards\Models\RewardBenefitItem;
use Carbon\CarbonImmutable;

/**
 * Coupon-fulfillment boundary for benefit grants.
 *
 * Only the coupon adapter needs this contract; manual handoff fulfillment,
 * consumption, and revocation never touch it. The default binding fails
 * closed: coupon-type benefits throw with a clear exception while
 * non-coupon benefits are unaffected. Hosts with a coupon/voucher system
 * bind an implementation that issues recipient-bound single-use coupons.
 */
interface RewardBenefitCouponFulfillment
{
    /**
     * Issue the coupon backing the grant, exactly once per grant key.
     * Returns the issued coupon code stored as the grant external ref.
     */
    public function issue(RewardBenefitGrant $grant, RewardBenefitItem $item, CarbonImmutable $decided): string;

    /**
     * Assert canonical coupon use: the cited (or auto-resolved) usage must
     * belong to the issued coupon AND to the grant recipient. Throws when
     * no matching usage exists — issuance is never confused with use.
     *
     * @param  array<string, mixed>  $useEvidence
     */
    public function assertCanonicalUsage(RewardBenefitGrant $grant, array $useEvidence): void;

    /**
     * Revoke the issued coupon. Throws when the coupon is missing or was
     * already used — consumed value needs a recovery case, not revocation.
     */
    public function revoke(RewardBenefitGrant $grant): void;
}
