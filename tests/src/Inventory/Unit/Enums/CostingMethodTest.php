<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\CostingMethod;

/* Values/count covered by Unit/CostingMethodTest. */

it('can create costing method from value', function (): void {
    $method = CostingMethod::from('weighted_average');

    expect($method)->toBe(CostingMethod::WeightedAverage);
});

it('throws for invalid costing method value', function (): void {
    CostingMethod::from('invalid_method');
})->throws(ValueError::class);

it('can try from value', function (): void {
    $method = CostingMethod::tryFrom('fifo');
    $invalid = CostingMethod::tryFrom('invalid');

    expect($method)->toBe(CostingMethod::Fifo);
    expect($invalid)->toBeNull();
});

/* Labels/shortLabels/isPerpetual/requiresLayerTracking covered by Unit/CostingMethodTest. */
