<?php

declare(strict_types=1);

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Product;

function autoCollection(array $conditions): Collection
{
    return Collection::create([
        'name' => 'Ruled Collection',
        'type' => 'automatic',
        'conditions' => $conditions,
    ]);
}

it('rejects conditions probing guarded columns', function (string $field): void {
    $collection = autoCollection([['field' => $field, 'operator' => '=', 'value' => 'x']]);

    expect(fn () => $collection->getMatchingProducts())->toThrow(InvalidArgumentException::class);
})->with(['cost', 'metadata', 'owner_id', 'owner_type', 'no_such_column']);

it('rejects conditions with unknown operators or non-scalar values', function (): void {
    $badOperator = autoCollection([['field' => 'name', 'operator' => 'regexp', 'value' => 'x']]);
    $badValue = autoCollection([['field' => 'name', 'operator' => '=', 'value' => ['x']]]);

    expect(fn () => $badOperator->getMatchingProducts())->toThrow(InvalidArgumentException::class)
        ->and(fn () => $badValue->getMatchingProducts())->toThrow(InvalidArgumentException::class);
});

it('filters by an allowed direct column', function (): void {
    $collection = autoCollection([['field' => 'status', 'operator' => '=', 'value' => 'active']]);

    Product::create(['name' => 'Ruled Active', 'price' => 1000, 'status' => ProductStatus::Active]);

    expect($collection->getMatchingProducts()->where('name', 'Ruled Active'))->toHaveCount(1);
});

it('rebuilds automatic memberships and drops stale pivots', function (): void {
    $collection = autoCollection([['field' => 'is_featured', 'operator' => '=', 'value' => true]]);

    $product = Product::create(['name' => 'Rebuild Featured', 'price' => 1000, 'status' => ProductStatus::Active, 'is_featured' => true]);

    $collection->rebuildProductList();

    expect($collection->products()->count())->toBe(1);

    $product->update(['is_featured' => false]);

    $collection->rebuildProductList();

    expect($collection->products()->count())->toBe(0);
});
