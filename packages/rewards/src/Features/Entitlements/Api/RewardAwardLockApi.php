<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Api;

interface RewardAwardLockApi
{
    public function assertAwardInScopeWhenExists(string $awardId): void;

    public function assertAwardProgram(string $awardId, string $programId): void;

    /** @param list<string> $awardIds */
    public function assertAwardsInScopeWhenExist(array $awardIds): void;

    /** Run system work under an existing award's program owner. */
    public function withAwardOwnerWhenExists(string $awardId, callable $callback): mixed;

    /** Read a scoped award decision; a locking read requires the caller's transaction. */
    public function findAwardDecision(string $awardId, bool $lock = false): ?RewardAwardDecision;

    /** Read frozen payout economics under the award lock in the caller's transaction. */
    public function lockPayoutAward(string $awardId): RewardPayoutAwardSnapshot;

    /** Lock a scoped award inside the caller's transaction. */
    public function lockScopedAward(string $awardId): void;

    /**
     * Validate the program scope, then lock awards referenced by immutable debit lots before locking the lots.
     *
     * @param  list<string>  $awardIds
     * @return list<string>
     */
    public function lockAndFindReversedAwards(array $awardIds): array;
}
