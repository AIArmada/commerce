<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Pipeline\Resolvers;

use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipelinePhaseContext;

final class DefaultScopeResolver implements ConditionScopeResolverInterface
{
    public function __construct(private readonly ConditionScope $scope) {}

    public function supports(ConditionScope $scope): bool
    {
        return $this->scope === $scope;
    }

    public function resolve(
        ConditionPipelinePhaseContext $phaseContext,
        ConditionScope $scope,
        CartConditionCollection $conditions,
        float $currentAmount
    ): float {
        return $conditions
            ->sortByOrder()
            ->reduce(
                static fn (float $amount, CartCondition $condition) => $condition->apply($amount),
                $currentAmount
            );
    }
}
