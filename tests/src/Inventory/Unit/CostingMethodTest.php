<?php

declare(strict_types=1);

use AIArmada\Inventory\Services\Costing\FifoCostingMethod;
use AIArmada\Inventory\Services\Costing\StandardCostingMethod;
use AIArmada\Inventory\Services\Costing\ValuationService;
use AIArmada\Inventory\Services\Costing\WeightedAverageCostingMethod;

test('the provider resolves named costing adapters', function (): void {
    expect(app(FifoCostingMethod::class))->toBeInstanceOf(FifoCostingMethod::class)
        ->and(app(WeightedAverageCostingMethod::class))->toBeInstanceOf(WeightedAverageCostingMethod::class)
        ->and(app(StandardCostingMethod::class))->toBeInstanceOf(StandardCostingMethod::class)
        ->and(app(ValuationService::class))->toBeInstanceOf(ValuationService::class);
});
