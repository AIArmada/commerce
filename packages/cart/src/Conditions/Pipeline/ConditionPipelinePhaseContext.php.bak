<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Pipeline;

use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;

final class ConditionPipelinePhaseContext
{
    public function __construct(
        public readonly ConditionPhase $phase,
        public readonly float $baseAmount,
        public readonly CartConditionCollection $conditions,
        public readonly ConditionPipelineContext $pipelineContext
    ) {}

    public function isEmpty(): bool
    {
        return $this->conditions->isEmpty();
    }
}
