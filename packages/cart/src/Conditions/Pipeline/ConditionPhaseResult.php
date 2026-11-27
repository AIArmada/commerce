<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Pipeline;

use AIArmada\Cart\Conditions\Enums\ConditionPhase;

final readonly class ConditionPhaseResult
{
    public function __construct(
        public ConditionPhase $phase,
        public int $baseAmount,
        public int $finalAmount,
        public int $adjustment,
        public int $appliedConditions
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'phase' => $this->phase->value,
            'base_amount' => $this->baseAmount,
            'final_amount' => $this->finalAmount,
            'adjustment' => $this->adjustment,
            'applied_conditions' => $this->appliedConditions,
        ];
    }
}
