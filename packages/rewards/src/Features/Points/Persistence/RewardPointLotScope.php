<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Points\Persistence;

use AIArmada\Rewards\Features\Entitlements\Api\RewardAwardLockApi;
use AIArmada\Rewards\Features\Points\Models\RewardPointLot;
use AIArmada\Rewards\Features\Points\Models\RewardPointMovement;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramLockApi;
use InvalidArgumentException;

final class RewardPointLotScope
{
    /** @param iterable<RewardPointLot> $lots */
    public static function assertAccessible(iterable $lots): void
    {
        $awardIds = [];
        $programIds = [];

        foreach ($lots as $lot) {
            if ($lot->award_id !== null) {
                $awardIds[$lot->award_id] = true;
            }

            if ($lot->program_id !== null) {
                $programIds[$lot->program_id] = true;
            }
        }

        $awards = app(RewardAwardLockApi::class);
        $programs = app(RewardProgramLockApi::class);

        $awards->assertAwardsInScopeWhenExist(array_keys($awardIds));

        foreach (array_keys($programIds) as $programId) {
            $programs->assertProgramInScopeWhenExists($programId);
        }
    }

    /** @param list<RewardPointMovement> $movements */
    public static function assertMovementLotsAccessible(array $movements): void
    {
        $ids = array_values(array_unique(array_map(
            static fn (RewardPointMovement $movement): string => $movement->lot_id,
            $movements,
        )));
        $lots = RewardPointLot::query()->whereIn('id', $ids)->get();

        if ($lots->count() !== count($ids)) {
            throw new InvalidArgumentException('Linked point lot is missing.');
        }

        self::assertAccessible($lots);
    }
}
