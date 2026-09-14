<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Actions\UpdateProductStatus;
use AIArmada\Products\Database\Factories\ProductFactory;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use Illuminate\Database\QueryException;

it('rejects unsupported currencies at write time', function (): void {
    expect(fn () => Product::create(['name' => 'Bad Currency', 'price' => 1000, 'currency' => 'XX']))
        ->toThrow(InvalidArgumentException::class, 'Invalid currency');
});

it('normalizes currency codes to uppercase', function (): void {
    $product = Product::create(['name' => 'Lower Currency', 'price' => 1000, 'currency' => 'usd']);

    expect($product->currency)->toBe('USD')
        ->and($product->getFormattedPrice())->toBeString();
});

it('reports duplicate identity with a domain exception while the database stays authoritative', function (): void {
    $owner = OwnerContext::resolve();

    Product::create([
        'name' => 'Identity One',
        'slug' => 'identity-clash',
        'price' => 1000,
        'owner_type' => $owner?->getMorphClass(),
        'owner_id' => $owner?->getKey(),
    ]);

    // Explicit owner tuples hit the friendly pre-check.
    expect(fn () => Product::create([
        'name' => 'Identity Two',
        'slug' => 'identity-clash',
        'price' => 1000,
        'owner_type' => $owner?->getMorphClass(),
        'owner_id' => $owner?->getKey(),
    ]))->toThrow(InvalidArgumentException::class, 'already used');

    // Auto-assigned creates rely on the partial unique index.
    expect(fn () => Product::create(['name' => 'Identity Three', 'slug' => 'identity-clash', 'price' => 1000]))
        ->toThrow(QueryException::class);

    // Beneath the model events the partial unique index still enforces.
    expect(fn () => Product::withoutEvents(fn (): Product => Product::query()->create([
        'name' => 'Identity Four',
        'slug' => 'identity-clash',
        'price' => 1000,
        'owner_type' => $owner?->getMorphClass(),
        'owner_id' => $owner?->getKey(),
    ])))->toThrow(QueryException::class);
});

it('creates identity indexes in every environment', function (): void {
    $migrations = glob(__DIR__ . '/../../../packages/products/database/migrations/*.php');

    expect($migrations)->not->toBeEmpty();

    foreach ($migrations as $migration) {
        expect(file_get_contents($migration))->not->toContain('app()->environment');
    }
});

it('clears the publish timestamp when returning to draft', function (): void {
    $product = Product::create(['name' => 'Draft Cycle', 'price' => 1000, 'status' => ProductStatus::Draft]);

    app(UpdateProductStatus::class)->execute($product, ProductStatus::Active);

    expect($product->fresh()->published_at)->not->toBeNull();

    app(UpdateProductStatus::class)->execute($product->fresh(), ProductStatus::Draft);

    expect($product->fresh()->published_at)->toBeNull()
        ->and($product->fresh()->status)->toBe(ProductStatus::Draft);
});

it('rejects malformed swatches and suppresses unsafe legacy styles', function (): void {
    $product = Product::create(['name' => 'Swatch Product', 'price' => 1000]);
    $option = Option::create(['product_id' => $product->id, 'name' => 'Finish']);

    expect(fn () => OptionValue::create(['option_id' => $option->id, 'name' => 'Bad', 'swatch_color' => 'red; x']))
        ->toThrow(InvalidArgumentException::class, 'swatch_color');

    expect(fn () => OptionValue::create(['option_id' => $option->id, 'name' => 'Bad', 'swatch_image' => "x');alert(1)//"]))
        ->toThrow(InvalidArgumentException::class, 'swatch_image');

    $legacy = OptionValue::withoutEvents(fn (): OptionValue => OptionValue::query()->create([
        'option_id' => $option->id, 'name' => 'Legacy', 'swatch_color' => 'javascript:alert(1)',
    ]));

    expect($legacy->getSwatchStyle())->toBeNull();
});

it('caches factory column listings per table', function (): void {
    $property = new ReflectionProperty(ProductFactory::class, 'columnListingCache');
    $property->setValue(null, []);

    Product::factory()->create();

    expect($property->getValue())->toHaveKey((new Product)->getTable());
});
