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
use AIArmada\Products\Services\CompositePackageService;
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

it('allows disabled tiers to be configured and retired without emptying an enabled variant', function (): void {
    $leaf = variantCompositeLeaf('Retired Tier Service', 150);
    $parent = variantCompositeParent('Retired Tier Lifecycle');
    $active = variantCompositeOption($parent, 'Live Plan');
    $retired = variantCompositeOption($parent, 'Retired Plan', ['is_enabled' => false]);

    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $leaf->id, 'quantity' => 1],
    ], $active);

    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $leaf->id, 'quantity' => 2],
    ], $retired);

    app(PublishCompositeProduct::class)->handle($parent);

    expect(fn () => app(ReplaceProductComponents::class)->handle($parent, [], $active))
        ->toThrow(InvalidCompositePackageException::class, 'at least one component');

    expect(app(ReplaceProductComponents::class)->handle($parent, [], $retired))->toHaveCount(0)
        ->and($active->fresh()->canBePurchased(1))->toBeTrue()
        ->and($retired->delete())->toBeTrue();
});

it('prevents changing variant composition mode after variants or component graphs exist', function (): void {
    $parent = variantCompositeParent('Immutable Variant Mode');
    variantCompositeOption($parent, 'Active Tier');

    expect(fn () => $parent->update(['supports_variants' => false]))
        ->toThrow(InvalidCompositePackageException::class, 'variant mode is immutable');
});

it('rejects foreign parent variants even when pricing or supplied components bypass graph queries', function (): void {
    $a = variantCompositeParent('First Fixed Price Graph');
    $b = variantCompositeParent('Second Fixed Price Graph');
    $variant = variantCompositeOption($b, 'Foreign Fixed Price', [
        'price_mode' => 'fixed',
        'price' => 999,
    ]);
    $service = app(CompositePackageService::class);
    $components = $service->componentsFor($a);

    expect(fn () => $service->priceFor($a, parentVariant: $variant))
        ->toThrow(InvalidCompositePackageException::class, 'must belong to the selected product')
        ->and(fn () => $service->priceModeFor($a, $variant))
        ->toThrow(InvalidCompositePackageException::class, 'must belong to the selected product')
        ->and(fn () => $service->snapshot($a, $components, $variant))
        ->toThrow(InvalidCompositePackageException::class, 'must belong to the selected product')
        ->and(fn () => $service->availability($a, $components, parentVariant: $variant))
        ->toThrow(InvalidCompositePackageException::class, 'must belong to the selected product')
        ->and(fn () => $service->assertPublishable($a, $components, $variant))
        ->toThrow(InvalidCompositePackageException::class, 'must belong to the selected product');
});

it('rejects supplied component graphs from another product or another variant', function (): void {
    $leaf = variantCompositeLeaf('Supplied Graph Leaf', 100);
    $parent = variantCompositeParent('Supplied Graph Parent');
    $other = variantCompositeParent('Other Graph Parent');
    $first = variantCompositeOption($parent, 'First Graph', ['price_mode' => 'fixed', 'price' => 500]);
    $second = variantCompositeOption($parent, 'Second Graph');
    $foreign = variantCompositeOption($other, 'Foreign Graph');
    $replace = app(ReplaceProductComponents::class);
    $ownComponents = $replace->handle($parent, [
        ['component_product_id' => $leaf->id, 'quantity' => 1],
    ], $first);
    $otherVariantComponents = $replace->handle($parent, [
        ['component_product_id' => $leaf->id, 'quantity' => 2],
    ], $second);
    $foreignComponents = $replace->handle($other, [
        ['component_product_id' => $leaf->id, 'quantity' => 3],
    ], $foreign);
    $service = app(CompositePackageService::class);

    expect($service->priceFor($parent, $ownComponents, $first))->toBe(500);

    foreach ([$otherVariantComponents, $foreignComponents] as $components) {
        expect(fn () => $service->priceFor($parent, $components, $first))
            ->toThrow(InvalidCompositePackageException::class, 'must belong to the selected product, variant and owner')
            ->and(fn () => $service->snapshot($parent, $components, $first))
            ->toThrow(InvalidCompositePackageException::class, 'must belong to the selected product, variant and owner')
            ->and(fn () => $service->availability($parent, $components, parentVariant: $first))
            ->toThrow(InvalidCompositePackageException::class, 'must belong to the selected product, variant and owner')
            ->and(fn () => $service->assertPublishable($parent, $components, $first))
            ->toThrow(InvalidCompositePackageException::class, 'must belong to the selected product, variant and owner');
    }
});

it('allows a disabled tier to contain draft components but prevents premature activation', function (): void {
    $liveLeaf = variantCompositeLeaf('Live Service', 100);
    $draftLeaf = variantCompositeLeaf('Draft Service', 150);
    $draftLeaf->update(['status' => ProductStatus::Draft]);

    $parent = variantCompositeParent('Draft Tier Configuration');
    $live = variantCompositeOption($parent, 'Live');
    $draft = variantCompositeOption($parent, 'Draft', ['is_enabled' => false]);

    $replace = app(ReplaceProductComponents::class);
    $replace->handle($parent, [['component_product_id' => $liveLeaf->id, 'quantity' => 1]], $live);
    $parent = app(PublishCompositeProduct::class)->handle($parent);

    expect($replace->handle($parent, [
        ['component_product_id' => $draftLeaf->id, 'quantity' => 1],
    ], $draft))->toHaveCount(1);

    expect(fn () => $draft->update(['is_enabled' => true]))
        ->toThrow(InvalidCompositePackageException::class, 'must be active')
        ->and($draft->refresh()->is_enabled)->toBeFalse();
});
