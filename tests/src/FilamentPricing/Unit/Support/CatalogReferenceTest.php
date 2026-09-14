<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentPricing\Support\CatalogReference;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Validation\ValidationException;

uses(TestCase::class);

it('accepts catalog references that are visible in the current owner scope', function (): void {
    config()->set('products.features.owner.enabled', true);
    config()->set('products.features.owner.include_global', false);

    $owner = User::query()->create(['name' => 'Catalog', 'email' => 'catalog-ref@example.com', 'password' => 'secret']);

    $product = OwnerContext::withOwner($owner, fn (): Product => Product::create(['name' => 'Catalog Product', 'price' => 1000]));
    $variant = OwnerContext::withOwner($owner, fn (): Variant => Variant::create([
        'product_id' => $product->id, 'name' => 'CV', 'sku' => 'CATALOG-V1',
    ]));

    $checked = OwnerContext::withOwner($owner, fn (): array => [
        CatalogReference::validateFormData(['priceable_type' => Product::class, 'priceable_id' => (string) $product->getKey()], 'priceable_type', 'priceable_id'),
        CatalogReference::validateFormData(['tierable_type' => Variant::class, 'tierable_id' => (string) $variant->getKey()], 'tierable_type', 'tierable_id'),
    ]);

    expect($checked[0]['priceable_type'])->toBe(Product::class)
        ->and($checked[1]['tierable_type'])->toBe(Variant::class);
});

it('rejects non-catalog morph types', function (): void {
    try {
        CatalogReference::validateFormData(
            ['priceable_type' => User::class, 'priceable_id' => 'x'],
            'priceable_type',
            'priceable_id',
        );

        $this->fail('Expected a ValidationException for a non-catalog type.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('priceable_type');
    }
});

it('rejects missing or cross-owner catalog rows', function (): void {
    config()->set('products.features.owner.enabled', true);
    config()->set('products.features.owner.include_global', false);

    $ownerA = User::query()->create(['name' => 'A', 'email' => 'catalog-ref-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'B', 'email' => 'catalog-ref-b@example.com', 'password' => 'secret']);

    $productA = OwnerContext::withOwner($ownerA, fn (): Product => Product::create(['name' => 'Owned Catalog', 'price' => 1000]));

    OwnerContext::withOwner($ownerB, function () use ($productA): void {
        expect(fn () => CatalogReference::validateFormData(
            ['priceable_type' => Product::class, 'priceable_id' => (string) $productA->getKey()],
            'priceable_type',
            'priceable_id',
        ))->toThrow(ValidationException::class);

        expect(fn () => CatalogReference::validateFormData(
            ['priceable_type' => Product::class, 'priceable_id' => (string) Str::uuid()],
            'priceable_type',
            'priceable_id',
        ))->toThrow(ValidationException::class);
    });
});
