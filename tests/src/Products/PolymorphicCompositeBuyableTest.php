<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\Buyable;
use AIArmada\CommerceSupport\Support\CompositeComponents;
use AIArmada\Products\Actions\CreateCompositeProduct;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Exceptions\InvalidCompositePackageException;
use AIArmada\Products\Models\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class CompositeExternalSellable extends Model implements Buyable
{
    use HasUuids;

    protected $table = 'test_composite_external_sellables';

    public $timestamps = false;

    protected $guarded = [];

    public function getBuyableIdentifier(): string
    {
        return (string) $this->getKey();
    }

    public function getBuyableCatalogIdentifier(): ?string
    {
        return null;
    }

    public function getBuyableName(): string
    {
        return (string) $this->getAttribute('title');
    }

    public function getBuyablePrice(): int
    {
        return (int) $this->getAttribute('price');
    }

    public function getBuyableCurrency(): string
    {
        return 'MYR';
    }

    public function getBuyableAttributes(): array
    {
        return ['sku' => 'EXTERNAL-' . $this->getKey(), 'is_taxable' => true, 'requires_shipping' => false];
    }

    public function canBePurchased(?int $quantity = null): bool
    {
        return true;
    }

    public function getBuyableStock(): ?int
    {
        return null;
    }

    public function getBuyableMinimumQuantity(): int
    {
        return 1;
    }

    public function getBuyableMaximumQuantity(): ?int
    {
        return null;
    }

    public function getBuyableQuantityIncrement(): int
    {
        return 1;
    }
}

beforeEach(function (): void {
    Schema::create('test_composite_external_sellables', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->nullableUuidMorphs('owner');
        $table->string('title');
        $table->unsignedBigInteger('price');
    });

    config()->set('products.composite_buyable_types', ['external_component' => CompositeExternalSellable::class]);
    Relation::morphMap(['external_component' => CompositeExternalSellable::class]);
});

it('prices and flattens mixed Product and registered Buyable components', function (): void {
    $external = CompositeExternalSellable::query()->create([
        'id' => (string) Str::uuid(),
        'title' => 'External Time Slot',
        'price' => 300,
    ]);
    $product = Product::query()->create([
        'name' => 'Physical Product Component',
        'slug' => 'mixed-composite-product-' . Str::random(8),
        'sku' => 'MIXED-PRD-' . mb_strtoupper(Str::random(8)),
        'type' => ProductType::Service,
        'status' => ProductStatus::Active,
        'currency' => 'MYR',
        'price' => 200,
    ]);

    $package = app(CreateCompositeProduct::class)->handle([
        'name' => 'Mixed Domain Composite',
        'slug' => 'mixed-domain-composite-' . Str::random(8),
        'sku' => 'MIXED-CMP-' . mb_strtoupper(Str::random(8)),
        'status' => ProductStatus::Active,
        'price_mode' => 'sum',
    ], [
        ['component_product_id' => $product->id, 'quantity' => 1],
        ['buyable_type' => 'external_component', 'buyable_id' => $external->id, 'quantity' => 2],
    ]);

    $snapshot = $package->compositeSnapshot();
    $components = CompositeComponents::read($snapshot->entries);

    expect($package->compositePrice())->toBe(800)
        ->and($package->fresh()->requires_shipping)->toBeFalse()
        ->and($snapshot->entries)->toHaveCount(2)
        ->and($components[1]['product_id'])->toBeNull()
        ->and($components[1]['buyable_type'])->toBe('external_component')
        ->and($components[1]['buyable_id'])->toBe($external->id)
        ->and($components[1]['quantity'])->toBe(2)
        ->and($package->canBePurchased(3))->toBeTrue()
        ->and($package->getBuyableAttributes()['components'])->toBe($components);
});

it('rejects unregistered Buyable types and cross-owner component identities', function (): void {
    $external = CompositeExternalSellable::query()->create([
        'id' => (string) Str::uuid(),
        'title' => 'Invalid External',
        'price' => 100,
    ]);

    expect(fn () => app(CreateCompositeProduct::class)->handle([
        'name' => 'Invalid External Composite',
        'slug' => 'invalid-external-' . Str::random(8),
        'sku' => 'BAD-MIX-' . Str::random(8),
    ], [
        ['buyable_type' => 'unregistered', 'buyable_id' => $external->id, 'quantity' => 1],
    ]))->toThrow(InvalidCompositePackageException::class);
});
