<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Persistence;

use AIArmada\Referrals\Data\ReferralRewardContext;
use AIArmada\Referrals\Models\ReferralReward;

final readonly class ReferralRewardContextMapper
{
    public static function map(ReferralReward $reward, ?string $qualificationBusinessKey): ReferralRewardContext
    {
        return new ReferralRewardContext(
            id: $reward->id,
            inviteId: $reward->invite_id,
            programId: $reward->program_id,
            side: $reward->side->value,
            beneficiaryType: $reward->beneficiary_type,
            beneficiaryId: $reward->beneficiary_id,
            driver: $reward->driver,
            amountMinor: $reward->amount_minor,
            points: $reward->points,
            currency: $reward->currency,
            spec: $reward->spec,
            idempotencyKey: $reward->idempotency_key,
            externalReference: $reward->external_reference,
            metadata: $reward->metadata ?? [],
            qualificationBusinessKey: $qualificationBusinessKey,
        );
    }
}
