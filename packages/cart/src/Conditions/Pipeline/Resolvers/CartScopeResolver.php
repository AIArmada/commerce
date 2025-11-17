<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Pipeline\Resolvers;

use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipelinePhaseContext;

final class CartScopeResolver extends AbstractDatasetScopeResolver
{
    protected function scope(): ConditionScope
    {
        return ConditionScope::CART;
    }

    protected function fetchDatasets(ConditionPipelinePhaseContext $phaseContext): iterable
    {
        return [
            ['base_amount' => $phaseContext->baseAmount],
        ];
    }

    protected function extractBaseAmount(mixed $dataset): float
    {
        return (float) ($dataset['base_amount'] ?? 0);
    }
}
