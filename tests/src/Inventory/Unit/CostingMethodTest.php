<?php

declare(strict_types=1);

use AIArmada\Inventory\Features\Valuation\Persistence\FifoCostingMethod;
use AIArmada\Inventory\Features\Valuation\Persistence\StandardCostingMethod;
use AIArmada\Inventory\Features\Valuation\Persistence\ValuationService;
use AIArmada\Inventory\Features\Valuation\Persistence\WeightedAverageCostingMethod;

test('the provider resolves named costing adapters', function (): void {
    expect(app(FifoCostingMethod::class))->toBeInstanceOf(FifoCostingMethod::class)
        ->and(app(WeightedAverageCostingMethod::class))->toBeInstanceOf(WeightedAverageCostingMethod::class)
        ->and(app(StandardCostingMethod::class))->toBeInstanceOf(StandardCostingMethod::class)
        ->and(app(ValuationService::class))->toBeInstanceOf(ValuationService::class);
});
