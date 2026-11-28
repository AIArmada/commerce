<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Queries;

use AIArmada\Rewards\Data\RewardAwardSnapshot;
use AIArmada\Rewards\Features\Entitlements\Models\RewardAward;

final readonly class RewardAwardReadinessMapper
{
    public static function award(RewardAward $award): RewardAwardSnapshot
    {
        return new RewardAwardSnapshot(
            id: (string) $award->getKey(),
            programId: $award->program_id,
            recipientType: $award->recipient_type,
            recipientId: $award->recipient_id,
            component: $award->component->value,
            amountSnapshot: $award->amount_snapshot,
            obligationEpoch: $award->obligation_epoch,
            obligatedMinor: $award->obligated_minor,
        );
    }
}
