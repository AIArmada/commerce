<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Actions\CreateCompositeProduct;
use AIArmada\Products\Actions\PublishCompositeProduct;
use AIArmada\Products\Actions\ReplaceProductComponents;
use AIArmada\Products\Contracts\CompositeStockResolverInterface;
use AIArmada\Products\Enums\CompositePriceMode;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Exceptions\InvalidCompositePackageException;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\ProductComponent;
use AIArmada\Products\Models\Variant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

function makeCompositeParent(array $attributes = []): Product
{
    $name = $attributes['name'] ?? 'Composite ' . Str::random(8);

    return Product::create([
        'name' => $name,
        'sku' => $attributes['sku'] ?? 'PKG-' . mb_strtoupper(Str::random(8)),
        'type' => ProductType::Composite,
        'status' => ProductStatus::Draft,
        'price' => $attributes['price'] ?? 5000,
        'supports_variants' => false,
        'tracks_inventory' => false,
        ...$attributes,
    ]);
}

function makeComponentProduct(array $attributes = []): Product
{
    $name = $attributes['name'] ?? 'Component ' . Str::random(8);

    return Product::create([
        'name' => $name,
        'sku' => $attributes['sku'] ?? 'CMP-' . mb_strtoupper(Str::random(8)),
        'type' => ProductType::Simple,
        'status' => ProductStatus::Active,
        'price' => $attributes['price'] ?? 1000,
        'requires_shipping' => true,
        'tracks_inventory' => true,
        ...$attributes,
    ]);
}

function makeConfigurableComponent(array $attributes = []): Product
{
    return makeComponentProduct([
        'type' => ProductType::Configurable,
        'supports_variants' => true,
        ...$attributes,
    ]);
}

function makeComponentVariant(Product $product, array $attributes = []): Variant
{
    return Variant::create([
        'product_id' => $product->id,
        'name' => $attributes['name'] ?? 'Variant ' . Str::random(6),
        'sku' => $attributes['sku'] ?? 'VAR-' . mb_strtoupper(Str::random(8)),
        'price' => $attributes['price'] ?? null,
        'is_enabled' => $attributes['is_enabled'] ?? true,
        ...$attributes,
    ]);
}

final class CompositePackageCompositionStockResolver implements CompositeStockResolverInterface
{
    /** @var array<string, int> */
    public array $stock = [];

    public function availableFor(Model $inventoryable): int
    {
        return $this->stock[$inventoryable->getMorphClass() . ':' . $inventoryable->getKey()] ?? 0;
    }
}

it('creates an active composite package with real components and no parent inventory', function (): void {
    $mouse = makeComponentProduct(['sku' => 'MOUSE-BLACK', 'price' => 1500]);
    $cable = makeComponentProduct(['sku' => 'CABLE-USB', 'price' => 500]);

    $package = app(CreateCompositeProduct::class)->handle([
        'name' => 'Work From Home Kit',
        'sku' => 'WFH-KIT',
        'status' => ProductStatus::Active,
        'price' => 2999,
        'price_mode' => 'fixed',
    ], [
        ['component_product_id' => $mouse->id, 'quantity' => 2],
        ['component_product_id' => $cable->id, 'quantity' => 1],
    ]);

    expect($package->status)->toBe(ProductStatus::Active)
        ->and($package->type)->toBe(ProductType::Composite)
        ->and($package->tracks_inventory)->toBeFalse()
        ->and($package->supports_variants)->toBeFalse()
        ->and($package->published_at)->not->toBeNull()
        ->and($package->getAttribute('price_mode'))->toBe(CompositePriceMode::Fixed)
        ->and(ProductComponent::query()->where('product_id', $package->id)->count())->toBe(2);
});

it('forces composite parents to be non-inventory and non-variant identities', function (): void {
    $parent = Product::create([
        'name' => 'Invariant Kit',
        'sku' => 'INVARIANT-KIT',
        'type' => ProductType::Composite,
        'status' => ProductStatus::Draft,
        'supports_variants' => true,
        'tracks_inventory' => true,
    ]);

    expect($parent->fresh()->supports_variants)->toBeFalse()
        ->and($parent->fresh()->tracks_inventory)->toBeFalse()
        ->and($parent->fresh()->getAttribute('price_mode'))->toBe(CompositePriceMode::Fixed);
});

it('rolls back an active replacement that would leave the package empty', function (): void {
    $mouse = makeComponentProduct();
    $parent = makeCompositeParent();
    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $mouse->id, 'quantity' => 1],
    ]);
    $published = app(PublishCompositeProduct::class)->handle($parent);

    expect(fn () => app(ReplaceProductComponents::class)->handle($published, []))
        ->toThrow(InvalidCompositePackageException::class, 'at least one component');

    expect(ProductComponent::query()->where('product_id', $published->id)->count())->toBe(1);
});

it('rejects publishing an empty package and allows it after components are supplied', function (): void {
    $parent = makeCompositeParent(['sku' => 'EMPTY-KIT']);

    expect(fn () => app(PublishCompositeProduct::class)->handle($parent))
        ->toThrow(InvalidCompositePackageException::class, 'at least one component');

    $component = makeComponentProduct();

    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $component->id, 'quantity' => 1],
    ]);

    $published = app(PublishCompositeProduct::class)->handle($parent);

    expect($published->status)->toBe(ProductStatus::Active)
        ->and(ProductComponent::query()->where('product_id', $published->id)->count())->toBe(1);
});

it('blocks direct activation of an empty composite package', function (): void {
    $parent = makeCompositeParent();

    expect(fn () => $parent->update(['status' => ProductStatus::Active]))
        ->toThrow(InvalidCompositePackageException::class, 'at least one component');

    expect($parent->fresh()->status)->toBe(ProductStatus::Draft);
});

it('blocks deleting the last component from an active package', function (): void {
    $component = makeComponentProduct();
    $parent = makeCompositeParent();
    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $component->id, 'quantity' => 1],
    ]);
    $published = app(PublishCompositeProduct::class)->handle($parent);
    $row = ProductComponent::query()->where('product_id', $published->id)->firstOrFail();

    expect(fn () => $row->delete())
        ->toThrow(InvalidCompositePackageException::class, 'at least one component')
        ->and(ProductComponent::query()->where('product_id', $published->id)->count())->toBe(1);
});

it('replaces components transactionally without touching the old set on validation failure', function (): void {
    $mouse = makeComponentProduct(['sku' => 'KEEP-MOUSE']);
    $cable = makeComponentProduct(['sku' => 'BAD-CABLE']);
    $parent = makeCompositeParent();

    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $mouse->id, 'quantity' => 1],
    ]);

    expect(fn () => app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $mouse->id, 'quantity' => 2],
        ['component_product_id' => $cable->id, 'quantity' => 0],
    ]))->toThrow(InvalidCompositePackageException::class, 'at least one');

    $components = ProductComponent::query()->where('product_id', $parent->id)->get();

    expect($components)->toHaveCount(1)
        ->and($components->first()->component_product_id)->toBe($mouse->id)
        ->and($components->first()->quantity)->toBe(1);
});

it('rejects self references and nested composite components', function (): void {
    $parent = makeCompositeParent();
    $nested = makeCompositeParent(['sku' => 'NESTED-PKG']);

    expect(fn () => app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $parent->id, 'quantity' => 1],
    ]))->toThrow(InvalidCompositePackageException::class, 'cannot contain itself');

    expect(fn () => app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $nested->id, 'quantity' => 1],
    ]))->toThrow(InvalidCompositePackageException::class, 'Nested composite');
});

it('requires an exact variant for configurable components and rejects misplaced variants', function (): void {
    $configurable = makeConfigurableComponent();
    $variant = makeComponentVariant($configurable);
    $otherProduct = makeComponentProduct();
    $otherVariant = makeComponentVariant($otherProduct);
    $parent = makeCompositeParent();

    expect(fn () => app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $configurable->id, 'quantity' => 1],
    ]))->toThrow(InvalidCompositePackageException::class, 'exact variant');

    expect(fn () => app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $configurable->id, 'variant_id' => $otherVariant->id, 'quantity' => 1],
    ]))->toThrow(InvalidCompositePackageException::class, 'does not belong');

    $components = app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $configurable->id, 'variant_id' => $variant->id, 'quantity' => 1],
    ]);

    expect($components->first()->variant_id)->toBe($variant->id);

    $simple = makeComponentProduct();

    expect(fn () => app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $simple->id, 'variant_id' => $otherVariant->id, 'quantity' => 1],
    ]))->toThrow(InvalidCompositePackageException::class, 'Only configurable components');
});

it('rejects duplicate components, non-physical components, and inconsistent money or tax', function (): void {
    $mouse = makeComponentProduct();
    $parent = makeCompositeParent();

    expect(fn () => app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $mouse->id, 'quantity' => 1],
        ['component_product_id' => $mouse->id, 'quantity' => 1],
    ]))->toThrow(InvalidCompositePackageException::class, 'same component twice');

    $digital = makeComponentProduct([
        'type' => ProductType::Digital,
        'requires_shipping' => false,
        'tracks_inventory' => false,
    ]);

    expect(fn () => app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $digital->id, 'quantity' => 1],
    ]))->toThrow(InvalidCompositePackageException::class, 'physical product');

    $otherCurrency = makeComponentProduct(['currency' => 'USD']);

    expect(fn () => app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $otherCurrency->id, 'quantity' => 1],
    ]))->toThrow(InvalidCompositePackageException::class, 'package currency');

    $otherTax = makeComponentProduct(['is_taxable' => false]);

    expect(fn () => app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $otherTax->id, 'quantity' => 1],
    ]))->toThrow(InvalidCompositePackageException::class, 'same tax treatment');
});

it('validates quantity and immutable identity even on direct model writes', function (): void {
    $mouse = makeComponentProduct();
    $parent = makeCompositeParent();
    $row = ProductComponent::create([
        'product_id' => $parent->id,
        'component_product_id' => $mouse->id,
        'quantity' => 1,
    ]);

    expect(fn () => $row->update(['quantity' => 0]))
        ->toThrow(InvalidCompositePackageException::class, 'at least one');

    $other = makeComponentProduct();

    expect(fn () => $row->update(['component_product_id' => $other->id]))
        ->toThrow(InvalidArgumentException::class, 'immutable');
});

it('isolates composite package reads and writes by owner', function (): void {
    $ownerA = User::query()->create(['name' => 'Package A', 'email' => 'package-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Package B', 'email' => 'package-b@example.com', 'password' => 'secret']);

    $parentA = OwnerContext::withOwner($ownerA, fn (): Product => makeCompositeParent());
    $componentA = OwnerContext::withOwner($ownerA, fn (): Product => makeComponentProduct());
    $componentB = OwnerContext::withOwner($ownerB, fn (): Product => makeComponentProduct());

    OwnerContext::withOwner($ownerA, function () use ($parentA, $componentA): void {
        app(ReplaceProductComponents::class)->handle($parentA, [
            ['component_product_id' => $componentA->id, 'quantity' => 1],
        ]);
    });

    expect(OwnerContext::withOwner($ownerB, fn (): int => ProductComponent::query()->count()))->toBe(0)
        ->and(OwnerContext::withOwner($ownerA, fn (): int => ProductComponent::query()->count()))->toBe(1);

    OwnerContext::withOwner($ownerA, function () use ($parentA, $componentB): void {
        expect(fn () => app(ReplaceProductComponents::class)->handle($parentA, [
            ['component_product_id' => $componentB->id, 'quantity' => 1],
        ]))->toThrow(AuthorizationException::class);
    });

    OwnerContext::withOwner($ownerB, function () use ($parentA): void {
        expect(fn () => app(ReplaceProductComponents::class)->handle($parentA, []))
            ->toThrow(InvalidCompositePackageException::class, 'not accessible');
    });
});

it('protects referenced component products and variants from deletion', function (): void {
    $configurable = makeConfigurableComponent();
    $variant = makeComponentVariant($configurable);
    $parent = makeCompositeParent();

    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $configurable->id, 'variant_id' => $variant->id, 'quantity' => 1],
    ]);

    expect(fn () => $configurable->delete())
        ->toThrow(InvalidCompositePackageException::class, 'referenced by a composite package');

    expect(fn () => $variant->delete())
        ->toThrow(InvalidCompositePackageException::class, 'referenced by a composite package');
});

it('removes component rows when the composite parent is deleted', function (): void {
    $mouse = makeComponentProduct();
    $parent = makeCompositeParent();
    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $mouse->id, 'quantity' => 1],
    ]);

    $parent->delete();

    expect(ProductComponent::query()->withoutOwnerScope()->where('product_id', $parent->id)->count())->toBe(0)
        ->and(Product::query()->whereKey($parent->id)->exists())->toBeFalse()
        ->and(Product::query()->whereKey($mouse->id)->exists())->toBeTrue();
});

it('wires composite base price and canonical buyable line data to the snapshot', function (): void {
    $mouse = makeComponentProduct([
        'sku' => 'LINE-MOUSE',
        'price' => 1500,
        'weight' => 0.2,
        'length' => 10,
        'width' => 5,
        'height' => 3,
    ]);
    $cable = makeComponentProduct(['sku' => 'LINE-CABLE', 'price' => 500]);
    $parent = makeCompositeParent(['sku' => 'LINE-KIT', 'price' => 2999]);

    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $mouse->id, 'quantity' => 2],
        ['component_product_id' => $cable->id, 'quantity' => 1],
    ]);

    $parent = $parent->fresh();

    expect($parent->getBasePrice())->toBe(2999)
        ->and($parent->getBuyablePrice())->toBe(2999);

    $attributes = $parent->getBuyableAttributes();

    expect($attributes['components'])->toHaveCount(2)
        ->and($attributes['components'][0])->toHaveKeys(['weight', 'dimensions'])
        ->and($attributes['components'][0]['weight'])->toBe(200)
        ->and($attributes['components'][0]['dimensions'])->toBe(['length' => 10, 'width' => 5, 'height' => 3])
        ->and($attributes['components_fingerprint'])->toBe($parent->compositeSnapshot()->fingerprint)
        ->and($attributes['weight'])->toBe(400);

    $parent->update(['price_mode' => CompositePriceMode::Sum]);
    $parent = $parent->fresh();

    expect($parent->getBasePrice())->toBe(3500)
        ->and($parent->getBuyablePrice())->toBe(3500);
});

it('leaves ordinary product buyable lines free of composition data', function (): void {
    $product = makeComponentProduct(['sku' => 'ORDINARY-LINE', 'price' => 1200]);

    $attributes = $product->getBuyableAttributes();

    expect($attributes)->not->toHaveKey('components')
        ->and($attributes)->not->toHaveKey('components_fingerprint')
        ->and($product->getBasePrice())->toBe(1200)
        ->and($product->getBuyablePrice())->toBe(1200);
});

it('reflects actual component availability through product stock helpers', function (): void {
    $resolver = new CompositePackageCompositionStockResolver;
    app()->instance(CompositeStockResolverInterface::class, $resolver);

    $mouse = makeComponentProduct(['sku' => 'STOCK-MOUSE']);
    $cable = makeComponentProduct(['sku' => 'STOCK-CABLE']);
    $parent = makeCompositeParent();

    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $mouse->id, 'quantity' => 2],
        ['component_product_id' => $cable->id, 'quantity' => 3],
    ]);

    $parent = app(PublishCompositeProduct::class)->handle($parent);

    $resolver->stock[$mouse->getMorphClass() . ':' . $mouse->getKey()] = 7;
    $resolver->stock[$cable->getMorphClass() . ':' . $cable->getKey()] = 5;

    expect($parent->getStockQuantity())->toBe(1)
        ->and($parent->getBuyableStock())->toBe(1)
        ->and($parent->isInStock())->toBeTrue()
        ->and($parent->hasStock(1))->toBeTrue()
        ->and($parent->hasStock(2))->toBeFalse()
        ->and($parent->canBePurchased(1))->toBeTrue()
        ->and($parent->canBePurchased(2))->toBeFalse();
});

it('reports zero package availability for a disabled component variant', function (): void {
    $resolver = new CompositePackageCompositionStockResolver;
    app()->instance(CompositeStockResolverInterface::class, $resolver);

    $configurable = makeConfigurableComponent();
    $variant = makeComponentVariant($configurable, ['is_enabled' => false]);
    $parent = makeCompositeParent();

    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $configurable->id, 'variant_id' => $variant->id, 'quantity' => 1],
    ]);

    $resolver->stock[$variant->getMorphClass() . ':' . $variant->getKey()] = 10;

    expect($parent->fresh()->compositeAvailability()->packages)->toBe(0);
});

it('blocks creating an active composite package without components', function (): void {
    expect(fn () => Product::create([
        'name' => 'Empty Active Kit',
        'sku' => 'EMPTY-ACTIVE-KIT',
        'type' => ProductType::Composite,
        'status' => ProductStatus::Active,
        'price' => 1000,
    ]))->toThrow(InvalidCompositePackageException::class, 'at least one component');
});

it('blocks retyping a composite parent that still has components', function (): void {
    $component = makeComponentProduct();
    $parent = makeCompositeParent();

    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $component->id, 'quantity' => 1],
    ]);

    expect(fn () => $parent->update(['type' => ProductType::Simple]))
        ->toThrow(InvalidCompositePackageException::class, 'cannot be retyped');

    expect($parent->fresh()->type)->toBe(ProductType::Composite);
});

it('blocks retyping or disabling products and variants referenced by a package', function (): void {
    $configurable = makeConfigurableComponent();
    $variant = makeComponentVariant($configurable);
    $parent = makeCompositeParent();

    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $configurable->id, 'variant_id' => $variant->id, 'quantity' => 1],
    ]);

    expect(fn () => $configurable->update(['type' => ProductType::Digital]))
        ->toThrow(InvalidCompositePackageException::class, 'component product');

    expect(fn () => $configurable->update(['status' => ProductStatus::Disabled]))
        ->toThrow(InvalidCompositePackageException::class, 'component product');

    expect(fn () => $variant->update(['is_enabled' => false]))
        ->toThrow(InvalidCompositePackageException::class, 'cannot be disabled');

    expect($variant->fresh()->is_enabled)->toBeTrue();
});
