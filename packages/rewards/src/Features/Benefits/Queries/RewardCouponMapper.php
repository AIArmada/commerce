<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Benefits\Queries;

use AIArmada\Rewards\Data\RewardCouponGrant;
use AIArmada\Rewards\Data\RewardCouponTerms;
use AIArmada\Rewards\Features\Benefits\Models\RewardBenefitGrant;
use AIArmada\Rewards\Features\Benefits\Models\RewardBenefitItem;

final readonly class RewardCouponMapper
{
    public static function grant(RewardBenefitGrant $grant): RewardCouponGrant
    {
        return new RewardCouponGrant(
            grantKey: $grant->grant_key,
            recipientType: $grant->recipient_type,
            recipientId: $grant->recipient_id,
            quantity: $grant->quantity,
            externalReference: $grant->external_ref,
        );
    }

    public static function terms(RewardBenefitItem $item): RewardCouponTerms
    {
        return new RewardCouponTerms(
            benefitKey: $item->benefit_key,
            title: $item->title,
            fulfillmentConfig: $item->fulfillment_config,
            expiresAt: $item->expires_at,
        );
    }
}
