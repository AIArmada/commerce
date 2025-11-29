<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Pipeline\Resolvers;

use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipelinePhaseContext;

final class PaymentScopeResolver extends AbstractDatasetScopeResolver
{
    protected function scope(): ConditionScope
    {
        return ConditionScope::PAYMENTS;
    }

    protected function fetchDatasets(ConditionPipelinePhaseContext $phaseContext): iterable
    {
        return $phaseContext->pipelineContext->cartHasPaymentsResolver()
            ? $phaseContext->pipelineContext->getPayments()
            : [];
    }

    /**
     * @param  array<mixed>  $datasets
     */
    protected function initialAmount(int $currentAmount, array $datasets): int
    {
        $sum = array_sum(array_map(fn ($dataset) => $this->extractBaseAmount($dataset), $datasets));
        $extra = max($sum - $currentAmount, 0);

        return $currentAmount + $extra;
    }

    protected function extractBaseAmount(mixed $dataset): int
    {
        if (is_array($dataset)) {
            return (int) ($dataset['base_amount'] ?? $dataset['amount'] ?? 0);
        }

        if (is_object($dataset)) {
            foreach (['getBaseAmount', 'baseAmount', 'getAmount', 'amount'] as $method) {
                if (method_exists($dataset, $method)) {
                    return (int) $dataset->{$method}();
                }
            }
        }

        return 0;
    }
}
