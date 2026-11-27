<?php

declare(strict_types=1);

namespace AIArmada\Cart\Runtime\Conditions\Pipeline\Resolvers;

use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Runtime\Conditions\Pipeline\ConditionPipelinePhaseContext;

interface ConditionScopeResolverInterface
{
    public function supports(ConditionScope $scope): bool;

    public function resolve(
        ConditionPipelinePhaseContext $phaseContext,
        ConditionScope $scope,
        CartConditionCollection $conditions,
        int $currentAmount
    ): int;
}
