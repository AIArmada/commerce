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
    /**
     * @param  array<string, mixed>  $rules
     * @return array{configuration: array<string, mixed>, is_variant: bool}|null
     */
    public function resolveComponentConfiguration(array $rules, string $component, string $outcome, string $role): ?array;

    public function evaluate(RewardPreviewContext $context): RewardPreviewResult;
}
