<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Pipeline;

use AIArmada\Cart\Conditions\Enums\ConditionPhase;

final class ConditionPipelineResult
{
    /**
     * @param  array<string, ConditionPhaseResult>  $phases
     */
    public function __construct(
        public readonly float $initialAmount,
        public readonly float $finalAmount,
        private array $phases
    ) {}

    /**
     * @return array<string, ConditionPhaseResult>
     */
    public function phases(): array
    {
        return $this->phases;
    }

    public function getPhaseResult(ConditionPhase|string $phase): ?ConditionPhaseResult
    {
        $key = $phase instanceof ConditionPhase ? $phase->value : (string) $phase;

        return $this->phases[$key] ?? null;
    }

    public function subtotal(): float
    {
        /** @phpstan-ignore-next-line */
        return $this->getPhaseResult(ConditionPhase::CART_SUBTOTAL)?->finalAmount
            ?? $this->initialAmount;
    }

    public function total(): float
    {
        /** @phpstan-ignore-next-line */
        return $this->getPhaseResult(ConditionPhase::GRAND_TOTAL)?->finalAmount
            ?? $this->finalAmount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'initial_amount' => $this->initialAmount,
            'final_amount' => $this->finalAmount,
            'phases' => array_map(
                static fn (ConditionPhaseResult $result) => $result->toArray(),
                $this->phases
            ),
        ];
    }
}
