<?php

declare(strict_types=1);

use AIArmada\Products\Actions\CreateCompositeProduct;
use AIArmada\Products\Contracts\CompositeCapacityDemandResolverInterface;
use AIArmada\Products\Contracts\CompositeCapacityResolverInterface;
use AIArmada\Products\Data\CompositeCapacityDemand;
use AIArmada\Products\Data\CompositeCapacityRequest;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Product;
use Illuminate\Support\Str;

final class ScopedTestCapacityResolver implements CompositeCapacityDemandResolverInterface
{
    /** @var array<string, int|null> */
    public array $capacity = [];

    public string $resource = 'shared-pool';

    public int $units = 1;

    /** @var list<array<string, mixed>> */
    public array $contexts = [];

    public function availableFor(CompositeCapacityRequest $request): ?int
    {
        return $this->demandFor($request)->available;
    }

    public function demandFor(CompositeCapacityRequest $request): CompositeCapacityDemand
    {
        $this->contexts[] = $request->context;
        $path = $request->context['composition_path'] ?? [];
        $scope = (string) ($path[0]['metadata']['window'] ?? $request->context['window'] ?? '');

        return new CompositeCapacityDemand(
            resourceKey: $this->resource,
            scopeKey: $scope,
            unitsPerComponent: $this->units,
            available: $this->capacity[$scope] ?? null,
        );
    }
}

function scopedProduct(string $label, ProductType $type = ProductType::Service): Product
{
    return Product::query()->create([
        'name' => $label,
        'slug' => Str::slug($label) . '-' . Str::random(8),
        'sku' => 'SCP-' . mb_strtoupper(Str::random(10)),
        'type' => $type,
        'status' => ProductStatus::Active,
        'price' => 100,
        'currency' => 'MYR',
    ]);
}

function scopedPackage(string $name, array $components): Product
{
    return app(CreateCompositeProduct::class)->handle([
        'name' => $name,
        'slug' => Str::slug($name) . '-' . Str::random(8),
        'sku' => 'SCP-PKG-' . mb_strtoupper(Str::random(8)),
        'status' => ProductStatus::Active,
        'price_mode' => 'sum',
    ], $components);
}

it('keeps distinct windows independent for the same underlying Product', function (): void {
    $resolver = new ScopedTestCapacityResolver;
    $resolver->capacity = ['morning' => 4, 'evening' => 6];
    app()->instance(CompositeCapacityResolverInterface::class, $resolver);

    $service = scopedProduct('Scoped Service');
    $morning = scopedPackage('Morning Subpackage', [
        ['component_product_id' => $service->id, 'quantity' => 2],
    ]);
    $evening = scopedPackage('Evening Subpackage', [
        ['component_product_id' => $service->id, 'quantity' => 2],
    ]);
    $root = scopedPackage('Scoped Root', [
        ['component_product_id' => $morning->id, 'quantity' => 1, 'metadata' => ['window' => 'morning']],
        ['component_product_id' => $evening->id, 'quantity' => 1, 'metadata' => ['window' => 'evening']],
    ]);

    $availability = $root->compositeAvailability();

    expect($availability->packages)->toBe(2)
        ->and($availability->components)->toHaveCount(2)
        ->and($availability->components[0]['resource_key'])->toBe('shared-pool')
        ->and($availability->components[0]['scope_key'])->toBe('morning')
        ->and($availability->components[0]['quantity'])->toBe(2)
        ->and($availability->components[1]['scope_key'])->toBe('evening')
        ->and($availability->components[1]['quantity'])->toBe(2);
});

it('aggregates different products sharing a capacity resource before calculating sellable packages', function (): void {
    $resolver = new ScopedTestCapacityResolver;
    $resolver->capacity = ['' => 10];
    app()->instance(CompositeCapacityResolverInterface::class, $resolver);

    $first = scopedProduct('Shared Pool One');
    $second = scopedProduct('Shared Pool Two');
    $package = scopedPackage('Shared Pool Package', [
        ['component_product_id' => $first->id, 'quantity' => 2],
        ['component_product_id' => $second->id, 'quantity' => 3],
    ]);

    $availability = $package->compositeAvailability();

    expect($availability->packages)->toBe(2)
        ->and($availability->components)->toHaveCount(1)
        ->and($availability->components[0]['quantity'])->toBe(5)
        ->and($availability->components[0]['available'])->toBe(10);
});

it('allows generic weighted capacity units for the same shared resource', function (): void {
    $resolver = new ScopedTestCapacityResolver;
    $resolver->capacity = ['' => 14];
    $resolver->units = 3;
    app()->instance(CompositeCapacityResolverInterface::class, $resolver);

    $service = scopedProduct('Weighted Service');
    $package = scopedPackage('Weighted Package', [
        ['component_product_id' => $service->id, 'quantity' => 2],
    ]);

    expect($package->compositeAvailability()->packages)->toBe(2)
        ->and($package->compositeAvailability()->components[0]['quantity'])->toBe(6);
});

it('rejects invalid capacity identities and negative availability', function (): void {
    expect(fn () => new CompositeCapacityDemand(resourceKey: ' ', scopeKey: 'x'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => new CompositeCapacityDemand(resourceKey: 'r', scopeKey: '', unitsPerComponent: 0))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => new CompositeCapacityDemand(resourceKey: 'r', scopeKey: '', available: -1))
        ->toThrow(InvalidArgumentException::class);
});
