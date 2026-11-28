<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Contracts;

use AIArmada\Rewards\Data\RewardPreviewContext;
use AIArmada\Rewards\Data\RewardPreviewResult;

/**
 * Pure deterministic policy computation over a point-in-time snapshot.
 * Implementations must not read the database, the clock, or request state.
 */
interface RewardPreviewEvaluator
{
    public function evaluate(RewardPreviewContext $context): RewardPreviewResult;
}
