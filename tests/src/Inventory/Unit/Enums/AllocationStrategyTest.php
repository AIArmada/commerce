<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\AllocationStrategy;

/* Values/count covered by Unit/AllocationStrategyTest. */

it('can create allocation strategy from value', function (): void {
    $strategy = AllocationStrategy::from('fifo');

    expect($strategy)->toBe(AllocationStrategy::FIFO);
});

it('throws for invalid allocation strategy value', function (): void {
    AllocationStrategy::from('invalid_strategy');
})->throws(ValueError::class);

it('can try from value', function (): void {
    $strategy = AllocationStrategy::tryFrom('priority');
    $invalid = AllocationStrategy::tryFrom('invalid');

    expect($strategy)->toBe(AllocationStrategy::Priority);
    expect($invalid)->toBeNull();
});

/* Labels/descriptions/allowsSplit covered by Unit/AllocationStrategyTest. */
