<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Contracts;

use AIArmada\Rewards\Support\RewardPreviewContext;
use AIArmada\Rewards\Support\RewardPreviewResult;

/**
 * Pure deterministic policy computation over a point-in-time snapshot.
 * Implementations must not read the database, the clock, or request state.
 */
interface RewardPreviewEvaluator
{
    public function evaluate(RewardPreviewContext $context): RewardPreviewResult;
}
