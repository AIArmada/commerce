<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\CompositeComponents;
use AIArmada\Products\Actions\CreateCompositeProduct;
use AIArmada\Products\Actions\ReplaceProductComponents;
use AIArmada\Products\Contracts\CompositeCapacityResolverInterface;
use AIArmada\Products\Contracts\CompositeStockResolverInterface;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\ProductComponent;
use AIArmada\Products\Models\Variant;
use AIArmada\Products\Services\CompositePackageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

function genericComponent(ProductType $type, string $label, array $attributes = []): Product
{
    return Product::query()->create([
        'name' => $label,
        'slug' => Str::slug($label) . '-' . Str::random(8),
        'sku' => 'GEN-' . mb_strtoupper(Str::random(10)),
        'type' => $type,
        'status' => ProductStatus::Active,
        'price' => 1000,
        'currency' => 'MYR',
        ...$attributes,
    ]);
}

final class GenericCompositeCapacityResolver implements CompositeStockResolverInterface
{
    /** @var array<string, int|null> */
    public array $capacity = [];

    public function availableFor(Model $inventoryable): ?int
    {
        return $this->capacity[(string) $inventoryable->getKey()] ?? null;
    }
}

it('sells an entirely non-shipping package without fake stock or shipments', function (): void {
    $service = genericComponent(ProductType::Service, 'Consultation');
    $digital = genericComponent(ProductType::Digital, 'Download', ['price' => 350]);

    $package = app(CreateCompositeProduct::class)->handle([
        'name' => 'Consultation and Download',
        'slug' => 'consultation-and-download',
        'sku' => 'GEN-SERVICE-KIT',
        'status' => ProductStatus::Active,
        'price_mode' => 'sum',
    ], [
        [
            'component_product_id' => $service->id,
            'quantity' => 2,
            'unit_price' => 900,
            'metadata' => ['role' => 'primary'],
        ],
        ['component_product_id' => $digital->id, 'quantity' => 1],
    ]);

    $package = $package->fresh();
    $snapshot = $package->compositeSnapshot();
    $availability = $package->compositeAvailability();

    expect($package->requires_shipping)->toBeFalse()
        ->and($package->tracks_inventory)->toBeFalse()
        ->and($package->getBasePrice())->toBe(2150)
        ->and($package->getBuyableStock())->toBeNull()
        ->and($package->canBePurchased(999))->toBeTrue()
        ->and($availability->packages)->toBeNull()
        ->and($availability->limitingSku)->toBeNull()
        ->and($snapshot->entries)->toHaveCount(2)
        ->and($snapshot->entries[0]['unit_price'])->toBe(900)
        ->and($snapshot->entries[0]['metadata'])->toBe(['role' => 'primary'])
        ->and($snapshot->entries[0]['requires_shipping'])->toBeFalse()
        ->and($snapshot->entries[0]['tracks_inventory'])->toBeFalse()
        ->and($package->getBuyableAttributes()['requires_shipping'])->toBeFalse()
        ->and(CompositeComponents::read($snapshot->entries))->toBe($snapshot->entries);

    $before = $snapshot->fingerprint;
    $row = ProductComponent::query()->where('product_id', $package->id)->orderBy('position')->firstOrFail();
    $row->update(['metadata' => ['role' => 'secondary']]);

    expect($package->fresh()->compositeSnapshot()->fingerprint)->not->toBe($before);
});

it('limits mixed packages only by finite component capacity', function (): void {
    $resolver = new GenericCompositeCapacityResolver;
    app()->instance(CompositeStockResolverInterface::class, $resolver);

    $goods = genericComponent(ProductType::Simple, 'Printed Handbook', [
        'requires_shipping' => true,
        'tracks_inventory' => true,
    ]);
    $service = genericComponent(ProductType::Service, 'Onboarding Session');

    $package = app(CreateCompositeProduct::class)->handle([
        'name' => 'Handbook and Session',
        'slug' => 'handbook-and-session',
        'sku' => 'GEN-MIXED-KIT',
        'status' => ProductStatus::Active,
    ], [
        ['component_product_id' => $goods->id, 'quantity' => 3],
        ['component_product_id' => $service->id, 'quantity' => 2],
    ]);

    $resolver->capacity[(string) $goods->id] = 12;

    expect($package->fresh()->requires_shipping)->toBeTrue()
        ->and($package->getBuyableAttributes()['requires_shipping'])->toBeTrue()
        ->and($package->compositeAvailability()->packages)->toBe(4)
        ->and($package->compositeAvailability()->components[1]['available'])->toBeNull()
        ->and($package->getBuyableStock())->toBe(4);

    // Applications can impose a finite capacity even on an untracked service.
    $resolver->capacity[(string) $service->id] = 5;

    expect($package->compositeAvailability()->packages)->toBe(2)
        ->and($package->canBePurchased(3))->toBeFalse()
        ->and($package->canBePurchased(2))->toBeTrue();
});

it('derives shipping after replacing or directly deleting draft components', function (): void {
    $goods = genericComponent(ProductType::Simple, 'Physical Guide', [
        'requires_shipping' => true,
        'tracks_inventory' => true,
    ]);
    $service = genericComponent(ProductType::Service, 'Support Session');

    $package = app(CreateCompositeProduct::class)->handle([
        'name' => 'Changeable Package',
        'slug' => 'changeable-package',
        'sku' => 'GEN-CHANGEABLE-KIT',
    ], [
        ['component_product_id' => $goods->id, 'quantity' => 1],
    ]);

    expect($package->fresh()->requires_shipping)->toBeTrue();

    app(ReplaceProductComponents::class)->handle($package, [
        ['component_product_id' => $service->id, 'quantity' => 1],
    ]);

    expect($package->fresh()->requires_shipping)->toBeFalse();

    $row = ProductComponent::query()->where('product_id', $package->id)->firstOrFail();
    $row->delete();

    expect($package->fresh()->requires_shipping)->toBeFalse()
        ->and($package->fresh()->components()->count())->toBe(0);
});

it('accepts variants on any component that explicitly supports variants', function (): void {
    $service = genericComponent(ProductType::Service, 'Session Options', [
        'supports_variants' => true,
    ]);
    $variant = Variant::query()->create([
        'product_id' => $service->id,
        'name' => 'Extended Session',
        'sku' => 'GEN-EXTENDED-SESSION',
        'is_enabled' => true,
    ]);

    $package = app(CreateCompositeProduct::class)->handle([
        'name' => 'Extended Session Package',
        'slug' => 'extended-session-package',
        'sku' => 'GEN-VARIANT-KIT',
        'status' => ProductStatus::Active,
    ], [
        ['component_product_id' => $service->id, 'variant_id' => $variant->id, 'quantity' => 1],
    ]);

    expect($package->compositeSnapshot()->entries[0]['variant_id'])->toBe($variant->id)
        ->and($package->fresh()->requires_shipping)->toBeFalse();
});


final class ContextualCompositeResolver implements CompositeCapacityResolverInterface
{
    /** @var list<array<string, mixed>> */
    public array $receivedContext = [];

    /**
     * @param array<string, mixed> $context
     */
    public function availableFor(Product $parent, ProductComponent $component, array $context = []): ?int
    {
        $this->receivedContext[] = $context;

        return ($context['session'] ?? null) === 'unavailable' ? 0 : 8;
    }
}

it('passes generic availability context to the application resolver', function (): void {
    $resolver = new ContextualCompositeResolver;
    app()->instance(CompositeCapacityResolverInterface::class, $resolver);

    $service = genericComponent(ProductType::Service, 'Capacity limited consulting');
    $package = app(CreateCompositeProduct::class)->handle([
        'name' => 'Contextual Consulting Package',
        'slug' => 'contextual-consulting-package',
        'sku' => 'GEN-CONTEXT-KIT',
        'status' => ProductStatus::Active,
    ], [
        ['component_product_id' => $service->id, 'quantity' => 2, 'metadata' => ['segment' => 'standard']],
    ]);

    expect($package->compositeAvailability(['session' => 'morning'])->packages)->toBe(4)
        ->and($package->compositeAvailability(['session' => 'unavailable'])->packages)->toBe(0)
        ->and($resolver->receivedContext)->toBe([
            ['session' => 'morning'],
            ['session' => 'unavailable'],
        ]);
});
