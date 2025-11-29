<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Pipeline\Resolvers;

use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipelinePhaseContext;

abstract class AbstractDatasetScopeResolver implements ConditionScopeResolverInterface
{
    abstract protected function scope(): ConditionScope;

    /**
     * @return iterable<mixed>
     */
    abstract protected function fetchDatasets(ConditionPipelinePhaseContext $phaseContext): iterable;

    abstract protected function extractBaseAmount(mixed $dataset): float;

    final public function supports(ConditionScope $scope): bool
    {
        return $scope === $this->scope();
    }

    final public function resolve(
        ConditionPipelinePhaseContext $phaseContext,
        ConditionScope $scope,
        CartConditionCollection $conditions,
        float $currentAmount
    ): float {
        $datasets = $this->materialize($this->fetchDatasets($phaseContext));

        if ($datasets === []) {
            return $currentAmount + $this->applyAggregate($conditions, $currentAmount);
        }

        $aggregateConditions = $conditions->byApplication(ConditionApplication::AGGREGATE);
        $entryConditions = $conditions->reject(
            fn (CartCondition $condition) => $condition->getTargetDefinition()->application === ConditionApplication::AGGREGATE
        );

        $amount = $this->initialAmount($currentAmount, $datasets);

        if ($aggregateConditions->isNotEmpty()) {
            $base = array_sum(array_map(fn ($dataset) => $this->extractBaseAmount($dataset), $datasets));
            $amount += $this->applyAggregate($aggregateConditions, $base);
        }

        if ($entryConditions->isNotEmpty()) {
            foreach ($datasets as $dataset) {
                $base = $this->extractBaseAmount($dataset);
                $amount += $this->applyCollection($entryConditions, $base);
            }
        }

        return $amount;
    }

    /**
     * @param  array<mixed>  $datasets
     */
    protected function initialAmount(float $currentAmount, array $datasets): float
    {
        return $currentAmount;
    }

    private function applyAggregate(CartConditionCollection $conditions, float $base): float
    {
        return $this->applyAdjustment($conditions, $base);
    }

    private function applyCollection(CartConditionCollection $conditions, float $base): float
    {
        return $this->applyAdjustment($conditions, $base);
    }

    private function applyAdjustment(CartConditionCollection $conditions, float $base): float
    {
        $final = $conditions->sortByOrder()->reduce(
            static fn (float $carry, CartCondition $condition) => $condition->apply($carry),
            $base
        );

        return $final - $base;
    }

    /**
     * @param  iterable<mixed>  $datasets
     * @return array<int, mixed>
     */
    private function materialize(iterable $datasets): array
    {
        return is_array($datasets) ? $datasets : iterator_to_array($datasets, false);
    }
}
