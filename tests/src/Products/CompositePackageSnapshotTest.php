<?php

declare(strict_types=1);

use AIArmada\Products\Actions\CreateCompositeProduct;
use AIArmada\Products\Concerns\HasComponents;
use AIArmada\Products\Contracts\CompositeStockResolverInterface;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use AIArmada\Products\Services\CompositePackageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class CompositeConcernTestProduct extends Product
{
    use HasComponents;
}

final class CompositeTestStockResolver implements CompositeStockResolverInterface
{
    /** @var array<string, int> */
    public array $stock = [];

    public function availableFor(Model $inventoryable): int
    {
        return $this->stock[$inventoryable->getMorphClass() . ':' . $inventoryable->getKey()] ?? 0;
    }
}

function makeSnapshotComponent(array $attributes = []): Product
{
    $name = $attributes['name'] ?? 'Snapshot Component ' . Str::random(6);

    return Product::create([
        'name' => $name,
        'sku' => $attributes['sku'] ?? 'SNAP-' . mb_strtoupper(Str::random(8)),
        'type' => ProductType::Simple,
        'status' => ProductStatus::Active,
        'price' => $attributes['price'] ?? 1000,
        'requires_shipping' => true,
        'tracks_inventory' => true,
        'weight' => $attributes['weight'] ?? 1.25,
        'length' => $attributes['length'] ?? 10.5,
        'width' => $attributes['width'] ?? 6.25,
        'height' => $attributes['height'] ?? 3.75,
        'weight_unit' => 'kg',
        'dimension_unit' => 'cm',
        ...$attributes,
    ]);
}

it('builds a canonical composition snapshot with exact shared entry keys and units', function (): void {
    $mouse = makeSnapshotComponent(['sku' => 'MOUSE-BLACK', 'weight' => 1.25]);
    $keyboardProduct = makeSnapshotComponent([
        'sku' => 'KEYBOARD-BASE',
        'type' => ProductType::Configurable,
        'supports_variants' => true,
        'weight' => 2.0,
    ]);
    $keyboardVariant = Variant::create([
        'product_id' => $keyboardProduct->id,
        'name' => 'Keyboard US Layout',
        'sku' => 'KEYBOARD-US',
        'weight' => 0.25,
        'length' => 30.0,
        'width' => 20.0,
        'height' => 2.0,
        'is_enabled' => true,
    ]);

    $parent = app(CreateCompositeProduct::class)->handle([
        'name' => 'Workstation Kit',
        'sku' => 'WS-KIT',
        'status' => ProductStatus::Active,
        'price' => 12999,
        'price_mode' => 'fixed',
        'currency' => 'MYR',
    ], [
        ['product_id' => $mouse->id, 'quantity' => 2, 'position' => 0],
        ['product_id' => $keyboardProduct->id, 'variant_id' => $keyboardVariant->id, 'quantity' => 3, 'position' => 1],
    ]);

    $snapshot = app(CompositePackageService::class)->snapshot($parent);

    expect($snapshot->productId)->toBe($parent->id)
        ->and($snapshot->sku)->toBe('WS-KIT')
        ->and($snapshot->name)->toBe('Workstation Kit')
        ->and($snapshot->price)->toBe(12999)
        ->and($snapshot->currency)->toBe('MYR')
        ->and($snapshot->priceMode)->toBe('fixed')
        ->and($snapshot->fingerprint)->toHaveLength(64)
        ->and($snapshot->entries)->toHaveCount(2);

    $mouseEntry = $snapshot->entries[0];

    expect(array_keys($mouseEntry))->toBe([
        'product_id',
        'variant_id',
        'inventoryable_type',
        'inventoryable_id',
        'quantity',
        'sku',
        'name',
        'weight',
        'dimensions',
        'requires_shipping',
        'tracks_inventory',
    ])
        ->and($mouseEntry['product_id'])->toBe($mouse->id)
        ->and($mouseEntry['variant_id'])->toBeNull()
        ->and($mouseEntry['inventoryable_type'])->toBe(Product::class)
        ->and($mouseEntry['inventoryable_id'])->toBe($mouse->id)
        ->and($mouseEntry['quantity'])->toBe(2)
        ->and($mouseEntry['sku'])->toBe('MOUSE-BLACK')
        ->and($mouseEntry['weight'])->toBe(1250)
        ->and($mouseEntry['dimensions'])->toBe(['length' => 11, 'width' => 6, 'height' => 4])
        ->and($mouseEntry['requires_shipping'])->toBeTrue()
        ->and($mouseEntry['tracks_inventory'])->toBeTrue();

    $keyboardEntry = $snapshot->entries[1];

    expect($keyboardEntry['product_id'])->toBe($keyboardProduct->id)
        ->and($keyboardEntry['variant_id'])->toBe($keyboardVariant->id)
        ->and($keyboardEntry['inventoryable_type'])->toBe(Variant::class)
        ->and($keyboardEntry['inventoryable_id'])->toBe($keyboardVariant->id)
        ->and($keyboardEntry['quantity'])->toBe(3)
        ->and($keyboardEntry['sku'])->toBe('KEYBOARD-US')
        ->and($keyboardEntry['name'])->toBe('Keyboard US Layout')
        ->and($keyboardEntry['weight'])->toBe(250)
        ->and($keyboardEntry['dimensions'])->toBe(['length' => 30, 'width' => 20, 'height' => 2]);

    expect($snapshot->toArray())->toHaveKeys([
        'product_id',
        'variant_id',
        'sku',
        'name',
        'price',
        'currency',
        'price_mode',
        'entries',
        'fingerprint',
    ]);
});

it('prices fixed packages from the parent and sum packages from canonical component base prices', function (): void {
    $mouse = makeSnapshotComponent(['sku' => 'SUM-MOUSE', 'price' => 1000]);
    $keyboardProduct = makeSnapshotComponent([
        'sku' => 'SUM-KEYBOARD',
        'type' => ProductType::Configurable,
        'supports_variants' => true,
        'price' => 900,
    ]);
    $keyboardVariant = Variant::create([
        'product_id' => $keyboardProduct->id,
        'sku' => 'SUM-KEYBOARD-US',
        'price' => 250,
        'is_enabled' => true,
    ]);

    $sumPackage = app(CreateCompositeProduct::class)->handle([
        'name' => 'Sum Priced Kit',
        'sku' => 'SUM-KIT',
        'price' => 9999,
        'price_mode' => 'sum',
    ], [
        ['product_id' => $mouse->id, 'quantity' => 2],
        ['product_id' => $keyboardProduct->id, 'variant_id' => $keyboardVariant->id, 'quantity' => 3],
    ]);

    $fixedPackage = app(CreateCompositeProduct::class)->handle([
        'name' => 'Fixed Priced Kit',
        'sku' => 'FIXED-KIT',
        'price' => 4321,
        'price_mode' => 'fixed',
    ], [
        ['product_id' => $mouse->id, 'quantity' => 2],
    ]);

    $service = app(CompositePackageService::class);

    expect($service->priceFor($sumPackage))->toBe(2750)
        ->and($service->snapshot($sumPackage)->price)->toBe(2750)
        ->and($service->priceFor($fixedPackage))->toBe(4321)
        ->and($service->snapshot($fixedPackage)->price)->toBe(4321);
});

it('changes the snapshot fingerprint when current catalog contents or prices change', function (): void {
    $mouse = makeSnapshotComponent(['sku' => 'FINGERPRINT-MOUSE', 'price' => 1000]);
    $package = app(CreateCompositeProduct::class)->handle([
        'name' => 'Fingerprint Kit',
        'sku' => 'FP-KIT',
        'price_mode' => 'sum',
    ], [
        ['product_id' => $mouse->id, 'quantity' => 2],
    ]);

    $service = app(CompositePackageService::class);
    $before = $service->snapshot($package);

    $mouse->update(['price' => 1250]);

    $after = $service->snapshot($package->fresh());

    expect($before->matches($after))->toBeFalse()
        ->and($before->price)->toBe(2000)
        ->and($after->price)->toBe(2500)
        ->and($before->entries[0]['sku'])->toBe('FINGERPRINT-MOUSE');
});

it('reports package availability from the bottleneck actual inventory SKU', function (): void {
    $resolver = new CompositeTestStockResolver;
    app()->instance(CompositeStockResolverInterface::class, $resolver);

    $mouse = makeSnapshotComponent(['sku' => 'BOTTLENECK-MOUSE']);
    $cable = makeSnapshotComponent(['sku' => 'BOTTLENECK-CABLE']);
    $package = app(CreateCompositeProduct::class)->handle([
        'name' => 'Bottleneck Kit',
        'sku' => 'BOTTLENECK-KIT',
        'price_mode' => 'fixed',
    ], [
        ['product_id' => $mouse->id, 'quantity' => 2],
        ['product_id' => $cable->id, 'quantity' => 3],
    ]);

    $resolver->stock[$mouse->getMorphClass() . ':' . $mouse->getKey()] = 7;
    $resolver->stock[$cable->getMorphClass() . ':' . $cable->getKey()] = 5;

    $availability = app(CompositePackageService::class)->availability($package);

    expect($availability->packages)->toBe(1)
        ->and($availability->limitingSku)->toBe('BOTTLENECK-CABLE')
        ->and($availability->limitingAvailableQuantity)->toBe(5)
        ->and($availability->components)->toHaveCount(2)
        ->and($availability->components[1]['packages'])->toBe(1);
});

it('exposes composition helpers through the HasComponents concern', function (): void {
    $resolver = new CompositeTestStockResolver;
    app()->instance(CompositeStockResolverInterface::class, $resolver);

    $mouse = makeSnapshotComponent(['sku' => 'CONCERN-MOUSE', 'price' => 1000]);
    $parent = app(CreateCompositeProduct::class)->handle([
        'name' => 'Concern Kit',
        'sku' => 'CONCERN-KIT',
        'price_mode' => 'sum',
    ], [
        ['product_id' => $mouse->id, 'quantity' => 2],
    ]);

    $model = CompositeConcernTestProduct::query()->whereKey($parent->id)->firstOrFail();

    $resolver->stock[$mouse->getMorphClass() . ':' . $mouse->getKey()] = 6;

    expect($model->isCompositeProduct())->toBeTrue()
        ->and($model->components()->count())->toBe(1)
        ->and($model->compositePriceMode()->value)->toBe('sum')
        ->and($model->compositePrice())->toBe(2000)
        ->and($model->compositeSnapshot()->entries)->toHaveCount(1)
        ->and($model->compositeEntries()[0]['sku'])->toBe('CONCERN-MOUSE')
        ->and($model->compositeAvailability()->packages)->toBe(3);
});
