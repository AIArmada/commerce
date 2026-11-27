<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Pipeline;

use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;

final readonly class ConditionPipelinePhaseContext
{
    public function __construct(
        public ConditionPhase $phase,
        public int $baseAmount,
        public CartConditionCollection $conditions,
        public ConditionPipelineContext $pipelineContext
    ) {}

    public function isEmpty(): bool
    {
        return $this->conditions->isEmpty();
    }
}
