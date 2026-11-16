<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\CompositeComponents;
use AIArmada\Products\Actions\CreateCompositeProduct;
use AIArmada\Products\Actions\PublishCompositeProduct;
use AIArmada\Products\Actions\ReplaceProductComponents;
use AIArmada\Products\Contracts\CompositeCapacityResolverInterface;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Exceptions\InvalidCompositePackageException;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\ProductComponent;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\Support\PackageUnit;
use Illuminate\Support\Str;

function nestedLeaf(ProductType $type, string $name, int $price): Product
{
    return Product::query()->create([
        'name' => $name,
        'slug' => Str::slug($name) . '-' . Str::random(10),
        'sku' => 'NEST-' . mb_strtoupper(Str::random(12)),
        'type' => $type,
        'status' => ProductStatus::Active,
        'price' => $price,
        'currency' => 'MYR',
    ]);
}

function nestedPackage(string $name, string $mode, array $components, bool $active = true, int $price = 0): Product
{
    return app(CreateCompositeProduct::class)->handle([
        'name' => $name,
        'slug' => Str::slug($name) . '-' . Str::random(8),
        'sku' => 'PKG-' . mb_strtoupper(Str::random(12)),
        'price_mode' => $mode,
        'status' => $active ? ProductStatus::Active : ProductStatus::Draft,
        'price' => $price,
    ], $components);
}

final class NestedCompositeCapacityResolver implements CompositeCapacityResolverInterface
{
    /** @var array<string, int|null> */
    public array $capacities = [];

    /** @var list<array<string, mixed>> */
    public array $contexts = [];

    /** @param array<string, mixed> $context */
    public function availableFor(Product $parent, ProductComponent $component, array $context = []): ?int
    {
        $this->contexts[] = $context;

        return $this->capacities[$component->component_product_id] ?? null;
    }
}

it('flattens arbitrary composite depth with multiplicative quantities and preserves nesting provenance', function (): void {
    $physical = nestedLeaf(ProductType::Simple, 'Nested Monitor', 100);
    $service = nestedLeaf(ProductType::Service, 'Nested Setup', 50);

    $inner = nestedPackage('Nested Inner', 'sum', [
        ['component_product_id' => $physical->id, 'quantity' => 2],
        ['component_product_id' => $service->id, 'quantity' => 1],
    ]);
    $middle = nestedPackage('Nested Middle', 'sum', [
        ['component_product_id' => $inner->id, 'quantity' => 2],
    ]);
    $outer = nestedPackage('Nested Outer', 'sum', [
        ['component_product_id' => $middle->id, 'quantity' => 3],
        ['component_product_id' => $service->id, 'quantity' => 2],
    ]);

    $entries = $outer->compositeSnapshot()->entries;
    expect($outer->compositePrice())->toBe(1600) // (2 * 100 + 50) * 2 * 3 + 2 * 50
        ->and($outer->fresh()->requires_shipping)->toBeTrue()
        ->and($outer->tracksInventory())->toBeFalse()
        ->and($entries)->toHaveCount(3)
        ->and($entries[0]['product_id'])->toBe($physical->id)
        ->and($entries[0]['quantity'])->toBe(12)
        ->and($entries[1]['quantity'])->toBe(6)
        ->and($entries[2]['quantity'])->toBe(2)
        ->and($entries[0]['composition_path'])->toHaveCount(3)
        ->and($entries[0]['composition_path'][0]['product_id'])->toBe($middle->id)
        ->and($entries[0]['composition_path'][1]['product_id'])->toBe($inner->id)
        ->and($entries[0]['composition_path'][2]['product_id'])->toBe($physical->id)
        ->and(CompositeComponents::read($entries))->toBe($entries);

    $orderItem = new OrderItem;
    $orderItem->quantity = 1;
    $orderItem->metadata = ['requires_shipping' => true, 'components' => $entries];

    expect(PackageUnit::hasShippableComponents($orderItem))->toBeTrue()
        ->and((new PackageUnit($orderItem, 1))->components)->toHaveCount(3);
});

it('prices nested fixed, sum, and component-level overrides at the correct boundary', function (): void {
    $service = nestedLeaf(ProductType::Service, 'Nested Service Pricing', 80);
    $child = nestedPackage('Nested Fixed Child', 'fixed', [
        ['component_product_id' => $service->id, 'quantity' => 4],
    ], price: 125);
    $parent = nestedPackage('Nested Sum Parent', 'sum', [
        ['component_product_id' => $child->id, 'quantity' => 3],
    ]);

    expect($parent->compositePrice())->toBe(375)
        ->and($parent->compositeSnapshot()->entries[0]['quantity'])->toBe(12);

    app(ReplaceProductComponents::class)->handle($parent, [
        ['component_product_id' => $child->id, 'quantity' => 3, 'unit_price' => 90],
    ]);

    expect($parent->compositePrice())->toBe(270);

    $parent->update(['price_mode' => 'fixed', 'price' => 777]);
    expect($parent->compositePrice())->toBe(777);
});

it('aggregates shared leaf stock across separate nested branches instead of overselling', function (): void {
    $resolver = new NestedCompositeCapacityResolver;
    app()->instance(CompositeCapacityResolverInterface::class, $resolver);

    $stock = nestedLeaf(ProductType::Simple, 'Shared Nested Stock', 20);
    $left = nestedPackage('Left Nested Branch', 'sum', [
        ['component_product_id' => $stock->id, 'quantity' => 2],
    ]);
    $right = nestedPackage('Right Nested Branch', 'sum', [
        ['component_product_id' => $stock->id, 'quantity' => 3],
    ]);
    $root = nestedPackage('Shared Stock Root', 'sum', [
        ['component_product_id' => $left->id, 'quantity' => 2],
        ['component_product_id' => $right->id, 'quantity' => 1],
    ]);

    $resolver->capacities[(string) $stock->id] = 13;

    expect($root->compositeAvailability(['event' => 'expo'])->packages)->toBe(1)
        ->and($root->compositeAvailability()->components)->toHaveCount(1)
        ->and($root->compositeAvailability()->components[0]['quantity'])->toBe(7)
        ->and($root->hasStock(2))->toBeFalse()
        ->and($root->compositeSnapshot()->entries)->toHaveCount(2)
        ->and($root->compositeSnapshot()->entries[0]['quantity'])->toBe(4)
        ->and($root->compositeSnapshot()->entries[1]['quantity'])->toBe(3)
        ->and($resolver->contexts[0]['event'])->toBe('expo')
        ->and($resolver->contexts[0]['composition_path'])->toHaveCount(2);
});

it('rejects direct and indirect cycles without partially modifying component rows', function (): void {
    $a = nestedPackage('Cycle A', 'sum', [], active: false);
    $b = nestedPackage('Cycle B', 'sum', [], active: false);
    $c = nestedPackage('Cycle C', 'sum', [], active: false);

    app(ReplaceProductComponents::class)->handle($a, [
        ['component_product_id' => $b->id, 'quantity' => 1],
    ]);
    app(ReplaceProductComponents::class)->handle($b, [
        ['component_product_id' => $c->id, 'quantity' => 1],
    ]);

    expect(fn () => app(ReplaceProductComponents::class)->handle($c, [
        ['component_product_id' => $a->id, 'quantity' => 1],
    ]))->toThrow(InvalidCompositePackageException::class, 'cycle');

    expect($c->components()->count())->toBe(0)
        ->and($a->components()->count())->toBe(1)
        ->and($b->components()->count())->toBe(1);

    expect(fn () => app(ReplaceProductComponents::class)->handle($a, [
        ['component_product_id' => $a->id, 'quantity' => 1],
    ]))->toThrow(InvalidCompositePackageException::class);
});

it('blocks publishing composites that contain an unpublished nested composite', function (): void {
    $service = nestedLeaf(ProductType::Service, 'Unpublished Nested Leaf', 100);
    $child = nestedPackage('Unpublished Nested Child', 'sum', [
        ['component_product_id' => $service->id, 'quantity' => 1],
    ], active: false);
    $parent = nestedPackage('Unpublished Nested Parent', 'sum', [
        ['component_product_id' => $child->id, 'quantity' => 1],
    ], active: false);

    expect(fn () => app(PublishCompositeProduct::class)->handle($parent))
        ->toThrow(InvalidCompositePackageException::class, 'must be active');
});

it('updates ancestor shipping requirements when a nested child composition changes', function (): void {
    $service = nestedLeaf(ProductType::Service, 'Remote Nested Service', 50);
    $goods = nestedLeaf(ProductType::Simple, 'Shipped Nested Goods', 150);
    $child = nestedPackage('Mutable Nested Child', 'sum', [
        ['component_product_id' => $service->id, 'quantity' => 1],
    ]);
    $parent = nestedPackage('Mutable Nested Parent', 'sum', [
        ['component_product_id' => $child->id, 'quantity' => 1],
    ]);

    expect($parent->fresh()->requires_shipping)->toBeFalse();

    app(ReplaceProductComponents::class)->handle($child, [
        ['component_product_id' => $goods->id, 'quantity' => 1],
    ]);

    expect($child->fresh()->requires_shipping)->toBeTrue()
        ->and($parent->fresh()->requires_shipping)->toBeTrue()
        ->and($parent->compositeSnapshot()->entries[0]['requires_shipping'])->toBeTrue();
});

it('aggregates warehouse demand from flattened nested order snapshots', function (): void {
    $goods = nestedLeaf(ProductType::Simple, 'Warehouse Nested Item', 250);
    $service = nestedLeaf(ProductType::Service, 'Warehouse Nested Service', 80);
    $child = nestedPackage('Warehouse Nested Kit', 'sum', [
        ['component_product_id' => $goods->id, 'quantity' => 3],
        ['component_product_id' => $service->id, 'quantity' => 1],
    ]);
    $root = nestedPackage('Warehouse Nested Order', 'sum', [
        ['component_product_id' => $child->id, 'quantity' => 2],
        ['component_product_id' => $goods->id, 'quantity' => 1],
    ]);

    $orderItem = new OrderItem;
    $orderItem->quantity = 2;
    $orderItem->metadata = [
        'requires_shipping' => true,
        'components' => $root->compositeSnapshot()->entries,
    ];

    $demand = \AIArmada\Inventory\Cart\InventoryDemandResolver::forOrder([$orderItem]);

    expect($demand)->toHaveCount(1);

    $row = array_values($demand)[0];
    expect($row['model']->getKey())->toBe($goods->id)
        ->and($row['quantity'])->toBe(14);
});

it('preserves ancestor component overrides and metadata in immutable nested snapshots', function (): void {
    $service = nestedLeaf(ProductType::Service, 'Nested Audit Service', 150);
    $child = nestedPackage('Nested Audit Child', 'fixed', [
        ['component_product_id' => $service->id, 'quantity' => 2],
    ], price: 300);
    $parent = nestedPackage('Nested Audit Parent', 'fixed', [
        ['component_product_id' => $child->id, 'quantity' => 1, 'unit_price' => 275, 'metadata' => ['campaign' => 'fall']],
    ], price: 450);

    $original = $parent->compositeSnapshot();
    $path = $original->entries[0]['composition_path'];

    expect($path[0]['unit_price'])->toBe(275)
        ->and($path[0]['metadata'])->toBe(['campaign' => 'fall'])
        ->and(CompositeComponents::read($original->entries))->toBe($original->entries);

    $component = ProductComponent::query()->where('product_id', $parent->id)->firstOrFail();
    $component->update(['metadata' => ['campaign' => 'winter']]);

    $updated = $parent->compositeSnapshot();

    expect($updated->price)->toBe(450)
        ->and($updated->fingerprint)->not->toBe($original->fingerprint)
        ->and($updated->entries[0]['composition_path'][0]['metadata'])->toBe(['campaign' => 'winter']);
});

it('rejects a direct model mutation that would introduce a cycle', function (): void {
    $a = nestedPackage('Direct Mutation A', 'sum', [], active: false);
    $b = nestedPackage('Direct Mutation B', 'sum', [], active: false);

    app(ReplaceProductComponents::class)->handle($a, [
        ['component_product_id' => $b->id, 'quantity' => 1],
    ]);

    expect(fn () => ProductComponent::query()->create([
        'product_id' => $b->id,
        'component_product_id' => $a->id,
        'quantity' => 1,
    ]))->toThrow(InvalidCompositePackageException::class, 'cycle');

    expect($b->components()->count())->toBe(0);
});
