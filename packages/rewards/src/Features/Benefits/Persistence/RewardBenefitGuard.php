<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Benefits\Persistence;

use AIArmada\Rewards\Features\Benefits\Models\RewardBenefitGrant;
use AIArmada\Rewards\Features\Benefits\Models\RewardBenefitItem;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramLockApi;
use InvalidArgumentException;

final class RewardBenefitGuard
{
    /**
     * Resolve the benefit grant and prove its catalog item's program is in
     * the current owner scope.
     */
    public static function findGrantOrFail(RewardBenefitGrant | string $grant): RewardBenefitGrant
    {
        $id = $grant instanceof RewardBenefitGrant ? (string) $grant->getKey() : $grant;

        $resolved = RewardBenefitGrant::query()->whereKey($id)->first();

        if (! $resolved instanceof RewardBenefitGrant) {
            throw new InvalidArgumentException('Unknown reward benefit grant.');
        }

        self::findItemOrFail((string) $resolved->item_id);

        return $resolved;
    }

    /**
     * Resolve the catalog item and prove its program is in the current
     * owner scope.
     */
    public static function findItemOrFail(RewardBenefitItem | string $item): RewardBenefitItem
    {
        $id = $item instanceof RewardBenefitItem ? (string) $item->getKey() : $item;

        $resolved = RewardBenefitItem::query()->whereKey($id)->first();

        if (! $resolved instanceof RewardBenefitItem) {
            throw new InvalidArgumentException('Unknown reward benefit item.');
        }

        app(RewardProgramLockApi::class)->assertProgramInScope((string) $resolved->program_id);

        return $resolved;
    }
}
