<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\CompositeComponents;
use AIArmada\Products\Actions\CreateCompositeProduct;
use AIArmada\Products\Actions\PublishCompositeProduct;
use AIArmada\Products\Actions\ReplaceProductComponents;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Exceptions\InvalidCompositePackageException;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Support\Str;

function variantCompositeLeaf(string $name, int $price): Product
{
    return Product::query()->create([
        'name' => $name,
        'slug' => Str::slug($name) . '-' . Str::random(8),
        'sku' => 'VCP-' . mb_strtoupper(Str::random(8)),
        'type' => ProductType::Service,
        'status' => ProductStatus::Active,
        'price' => $price,
        'currency' => 'MYR',
    ]);
}

function variantCompositeParent(string $name): Product
{
    return app(CreateCompositeProduct::class)->handle([
        'name' => $name,
        'slug' => Str::slug($name) . '-' . Str::random(8),
        'sku' => 'VCP-PKG-' . mb_strtoupper(Str::random(8)),
        'status' => ProductStatus::Draft,
        'price_mode' => 'sum',
        'supports_variants' => true,
    ]);
}

function variantCompositeOption(Product $parent, string $name, array $attributes = []): Variant
{
    return Variant::query()->create([
        'product_id' => $parent->id,
        'name' => $name,
        'sku' => 'VCP-VAR-' . mb_strtoupper(Str::random(8)),
        'is_enabled' => true,
        ...$attributes,
    ]);
}

it('gives each composite variant its own sellable graph, price and fingerprint', function (): void {
    $basic = variantCompositeLeaf('Variant Basic Service', 100);
    $premium = variantCompositeLeaf('Variant Premium Service', 400);
    $parent = variantCompositeParent('Composite Plans');
    $bronze = variantCompositeOption($parent, 'Bronze');
    $gold = variantCompositeOption($parent, 'Gold', [
        'price_mode' => 'percentage_discount',
        'discount_basis_points' => 2500,
    ]);

    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $basic->id, 'quantity' => 2],
    ], $bronze);

    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $basic->id, 'quantity' => 2],
        ['component_product_id' => $premium->id, 'quantity' => 1],
    ], $gold);

    $parent = app(PublishCompositeProduct::class)->handle($parent);
    $bronze = $bronze->fresh();
    $gold = $gold->fresh();

    expect($parent->supportsVariants())->toBeTrue()
        ->and($parent->canBePurchased())->toBeFalse()
        ->and($bronze->canBePurchased(5))->toBeTrue()
        ->and($gold->canBePurchased(5))->toBeTrue()
        ->and($bronze->getBuyablePrice())->toBe(200)
        ->and($gold->getBuyablePrice())->toBe(450)
        ->and($bronze->getBuyableAttributes()['components'])->toHaveCount(1)
        ->and($gold->getBuyableAttributes()['components'])->toHaveCount(2)
        ->and($gold->getBuyableAttributes()['components_fingerprint'])->not->toBe($bronze->getBuyableAttributes()['components_fingerprint'])
        ->and(CompositeComponents::read($gold->getBuyableAttributes()['components']))->toHaveCount(2);
});

it('flattens nested variant composites using the selected child graph', function (): void {
    $service = variantCompositeLeaf('Nested Variant Service', 100);
    $parent = variantCompositeParent('Nested Plan Tiers');
    $bronze = variantCompositeOption($parent, 'Nested Bronze');
    $gold = variantCompositeOption($parent, 'Nested Gold');

    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $service->id, 'quantity' => 2],
    ], $bronze);
    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $service->id, 'quantity' => 4],
    ], $gold);
    app(PublishCompositeProduct::class)->handle($parent);

    $outer = app(CreateCompositeProduct::class)->handle([
        'name' => 'Outer Composite With Variant',
        'slug' => 'outer-comp-variant-' . Str::random(8),
        'sku' => 'VCP-OUT-' . mb_strtoupper(Str::random(8)),
        'status' => ProductStatus::Active,
        'price_mode' => 'sum',
    ], [
        ['component_product_id' => $parent->id, 'variant_id' => $gold->id, 'quantity' => 3],
    ]);

    expect($outer->compositePrice())->toBe(1200)
        ->and($outer->compositeSnapshot()->entries[0]['quantity'])->toBe(12)
        ->and($outer->compositeSnapshot()->entries[0]['composition_path'][0]['variant_id'])->toBe($gold->id);
});

it('does not allow a variant to change another product composite graph', function (): void {
    $a = variantCompositeParent('First Variant Graph');
    $b = variantCompositeParent('Second Variant Graph');
    $variant = variantCompositeOption($b, 'Wrong Parent Variant');
    $leaf = variantCompositeLeaf('Graph Isolation Leaf', 50);

    expect(fn () => app(ReplaceProductComponents::class)->handle($a, [
        ['component_product_id' => $leaf->id, 'quantity' => 1],
    ], $variant))->toThrow(InvalidCompositePackageException::class);
});

it('derives physical shipping weight and shipment requirement from the selected composite variant', function (): void {
    $goods = Product::query()->create([
        'name' => 'Physical Variant Merchandise',
        'slug' => 'physical-variant-merchandise-' . Str::random(8),
        'sku' => 'VAR-GOODS-' . mb_strtoupper(Str::random(8)),
        'type' => ProductType::Simple,
        'status' => ProductStatus::Active,
        'price' => 200,
        'currency' => 'MYR',
        'requires_shipping' => true,
        'tracks_inventory' => false,
        'weight' => 0.5,
        'weight_unit' => 'kg',
    ]);
    $parent = variantCompositeParent('Physical Variant Package');
    $variant = variantCompositeOption($parent, 'Physical Tier');

    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $goods->id, 'quantity' => 2],
    ], $variant);

    app(PublishCompositeProduct::class)->handle($parent);
    $attributes = $variant->fresh()->getBuyableAttributes();

    expect($attributes['requires_shipping'])->toBeTrue()
        ->and($attributes['weight'])->toBe(1000)
        ->and($attributes['components'][0]['requires_shipping'])->toBeTrue()
        ->and($attributes['components'][0]['quantity'])->toBe(2);
});

it('keeps published composite variants complete and preserves at least one enabled tier', function (): void {
    $leaf = variantCompositeLeaf('Published Tier Service', 100);
    $parent = variantCompositeParent('Published Tier Lifecycle');
    $bronze = variantCompositeOption($parent, 'Published Bronze');
    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $leaf->id, 'quantity' => 1],
    ], $bronze);
    app(PublishCompositeProduct::class)->handle($parent);

    expect(fn () => variantCompositeOption($parent, 'Incomplete New Tier'))
        ->toThrow(InvalidCompositePackageException::class, 'Create the variant disabled');

    expect(fn () => $bronze->update(['is_enabled' => false]))
        ->toThrow(InvalidCompositePackageException::class, 'at least one enabled variant');

    $silver = variantCompositeOption($parent, 'Pending Silver', ['is_enabled' => false]);
    expect(fn () => $silver->update(['is_enabled' => true]))
        ->toThrow(InvalidCompositePackageException::class, 'at least one component');

    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $leaf->id, 'quantity' => 2],
    ], $silver);

    expect($silver->update(['is_enabled' => true]))->toBeTrue()
        ->and($silver->fresh()->canBePurchased(1))->toBeTrue();

    expect($bronze->update(['is_enabled' => false]))->toBeTrue();

    expect(fn () => $silver->update(['is_enabled' => false]))
        ->toThrow(InvalidCompositePackageException::class, 'at least one enabled variant');

    expect(fn () => $silver->delete())
        ->toThrow(InvalidCompositePackageException::class, 'component graph');
});
