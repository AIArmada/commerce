<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Benefits\Api;

interface RewardBenefitRecoveryApi
{
    /**
     * Refuse reversal while a grant is still open. Lock grants in id order
     * within the caller's award transaction.
     *
     * @return list<ConsumedRewardBenefit>
     */
    public function consumedGrantsForReversal(string $awardId): array;

    public function assertGrantBelongsToAward(string $grantId, string $awardId): void;
}
