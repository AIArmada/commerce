<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Api;

interface RewardAwardLockApi
{
    /** Lock a scoped award inside the caller's transaction. */
    public function lockScopedAward(string $awardId): void;

    /**
     * Lock the awards referenced by immutable debit lots before locking the lots.
     *
     * @param  list<string>  $awardIds
     * @return list<string>
     */
    public function lockAndFindReversedAwards(array $awardIds): array;
}
