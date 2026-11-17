<?php

declare(strict_types=1);

use AIArmada\Products\Actions\CreateCompositeProduct;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Exceptions\InvalidCompositePackageException;
use AIArmada\Products\Models\Product;
use Illuminate\Support\Str;

function percentageFixtureProduct(string $name, int $price): Product
{
    return Product::query()->create([
        'name' => $name,
        'slug' => Str::slug($name) . '-' . Str::random(8),
        'sku' => 'PCT-' . mb_strtoupper(Str::random(8)),
        'price' => $price,
        'currency' => 'MYR',
        'type' => ProductType::Service,
        'status' => ProductStatus::Active,
    ]);
}

function percentageFixturePackage(string $name, array $components, int $basisPoints): Product
{
    return app(CreateCompositeProduct::class)->handle([
        'name' => $name,
        'slug' => Str::slug($name) . '-' . Str::random(8),
        'sku' => 'PCT-PKG-' . mb_strtoupper(Str::random(8)),
        'status' => ProductStatus::Active,
        'price_mode' => 'percentage_discount',
        'discount_basis_points' => $basisPoints,
    ], $components);
}

it('calculates percentage discounts in integer minor units, including per-edge prices', function (): void {
    $service = percentageFixtureProduct('Percentage Pricing Service', 1001);
    $package = percentageFixturePackage('Percentage 20% Package', [
        ['component_product_id' => $service->id, 'quantity' => 3, 'unit_price' => 999],
    ], 2000);

    // Gross = 2997; 20% = 599.4, rounded half up to 599.
    expect($package->compositePrice())->toBe(2398)
        ->and($package->getBuyablePrice())->toBe(2398)
        ->and($package->compositeSnapshot()->discountBasisPoints)->toBe(2000);
});

it('applies nested percentage discounts at each composite boundary', function (): void {
    $leaf = percentageFixtureProduct('Percentage Nested Leaf', 1001);
    $inner = percentageFixturePackage('Nested Discount 50%', [
        ['component_product_id' => $leaf->id, 'quantity' => 1],
    ], 5000); // 1001 - round(500.5) = 500

    $outer = percentageFixturePackage('Nested Discount 10%', [
        ['component_product_id' => $inner->id, 'quantity' => 3],
    ], 1000); // 1500 - 150 = 1350

    expect($inner->compositePrice())->toBe(500)
        ->and($outer->compositePrice())->toBe(1350)
        ->and($outer->compositeSnapshot()->entries)->toHaveCount(1)
        ->and($outer->compositeSnapshot()->entries[0]['quantity'])->toBe(3);
});

it('updates snapshot fingerprints when the discount changes even if rounding gives the same price', function (): void {
    $leaf = percentageFixtureProduct('Tiny Discount Leaf', 1);
    $package = percentageFixturePackage('Tiny Discount Package', [
        ['component_product_id' => $leaf->id, 'quantity' => 1],
    ], 100);

    $old = $package->compositeSnapshot()->fingerprint;
    $package->update(['discount_basis_points' => 200]);

    expect($package->fresh()->compositePrice())->toBe(1)
        ->and($package->fresh()->compositeSnapshot()->fingerprint)->not->toBe($old);
});

it('rejects discount percentages outside zero to one hundred percent', function (): void {
    $leaf = percentageFixtureProduct('Invalid Percentage Leaf', 500);
    expect(fn () => percentageFixturePackage('Invalid Percentage Package', [
        ['component_product_id' => $leaf->id, 'quantity' => 1],
    ], 10001))->toThrow(InvalidCompositePackageException::class);
});
