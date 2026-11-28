<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Queries;

use AIArmada\Rewards\Data\RewardAwardSnapshot;
use AIArmada\Rewards\Data\RewardVersionSnapshot;
use AIArmada\Rewards\Features\Entitlements\Models\RewardAward;
use AIArmada\Rewards\Models\RewardProgramVersion;

final readonly class RewardReadinessMapper
{
    public static function version(?RewardProgramVersion $version): ?RewardVersionSnapshot
    {
        return $version === null ? null : new RewardVersionSnapshot(
            id: (string) $version->getKey(),
            componentTypes: $version->componentTypes(),
            rules: $version->rules,
        );
    }

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
