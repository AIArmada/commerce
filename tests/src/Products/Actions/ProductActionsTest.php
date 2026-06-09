<?php

declare(strict_types=1);

use AIArmada\Products\Actions\ApplyAttributeChanges;
use AIArmada\Products\Actions\CreateProduct;
use AIArmada\Products\Actions\GenerateVariants;
use AIArmada\Products\Actions\UpdateProductStatus;
use AIArmada\Products\Enums\AttributeType;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Events\ProductCreated;
use AIArmada\Products\Events\ProductStatusChanged;
use AIArmada\Products\Events\VariantsGenerated;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Support\Facades\Event;

describe('CreateProduct Action', function (): void {
    it('creates a product with given attributes', function (): void {
        $product = app(CreateProduct::class)->execute([
            'name' => 'Action Created Product',
            'price' => 2500,
        ]);

        expect($product)->toBeInstanceOf(Product::class)
            ->and($product->name)->toBe('Action Created Product')
            ->and($product->price)->toBe(2500)
            ->and($product->exists)->toBeTrue();
    });

    it('dispatches ProductCreated event', function (): void {
        Event::fake([ProductCreated::class]);

        app(CreateProduct::class)->execute([
            'name' => 'Event Test Product',
            'price' => 1000,
        ]);

        Event::assertDispatched(ProductCreated::class);
    });

    it('can be invoked', function (): void {
        $action = app(CreateProduct::class);

        $product = $action([
            'name' => 'Invokable Product',
            'price' => 3000,
        ]);

        expect($product->name)->toBe('Invokable Product');
    });
});

describe('UpdateProductStatus Action', function (): void {
    it('changes product status and dispatches event', function (): void {
        Event::fake([ProductStatusChanged::class]);

        $product = Product::create(['name' => 'Status Test', 'price' => 1000]);

        app(UpdateProductStatus::class)->execute($product, ProductStatus::Active);

        expect($product->fresh()?->status)->toBe(ProductStatus::Active);

        Event::assertDispatched(ProductStatusChanged::class);
    });

    it('sets published_at when activating', function (): void {
        $product = Product::create(['name' => 'Publish Test', 'price' => 1000, 'status' => ProductStatus::Draft]);

        app(UpdateProductStatus::class)->execute($product, ProductStatus::Active);

        expect($product->fresh()?->published_at)->not->toBeNull();
    });

    it('preserves existing published_at when activating', function (): void {
        $publishedAt = now()->subDays(10);
        $product = Product::create(['name' => 'Pre Published', 'price' => 1000, 'status' => ProductStatus::Draft, 'published_at' => $publishedAt]);

        app(UpdateProductStatus::class)->execute($product, ProductStatus::Active);

        expect($product->fresh()?->published_at->format('Y-m-d'))->toBe($publishedAt->format('Y-m-d'));
    });

    it('does nothing when status is unchanged', function (): void {
        Event::fake([ProductStatusChanged::class]);

        $product = Product::create(['name' => 'No Change', 'price' => 1000, 'status' => ProductStatus::Draft]);

        app(UpdateProductStatus::class)->execute($product, ProductStatus::Draft);

        Event::assertNotDispatched(ProductStatusChanged::class);
    });

    it('does not double-dispatch through model booted listener', function (): void {
        Event::fake([ProductStatusChanged::class]);

        $product = Product::create(['name' => 'Double Dispatch', 'price' => 1000, 'status' => ProductStatus::Draft]);

        app(UpdateProductStatus::class)->execute($product, ProductStatus::Active);

        Event::assertDispatchedTimes(ProductStatusChanged::class, 1);
    });

    it('can be invoked', function (): void {
        $product = Product::create(['name' => 'Invokable Status', 'price' => 1000, 'status' => ProductStatus::Draft]);

        $action = app(UpdateProductStatus::class);
        $action($product, ProductStatus::Archived);

        expect($product->fresh()?->status)->toBe(ProductStatus::Archived);
    });
});

describe('GenerateVariants Action', function (): void {
    it('generates variant combinations from options', function (): void {
        $product = Product::create([
            'name' => 'Variant Product',
            'price' => 5000,
            'type' => ProductType::Configurable,
            'supports_variants' => true,
        ]);

        $color = Option::create(['product_id' => $product->id, 'name' => 'Color', 'position' => 1]);
        $size = Option::create(['product_id' => $product->id, 'name' => 'Size', 'position' => 2]);

        OptionValue::create(['option_id' => $color->id, 'name' => 'Red', 'position' => 1]);
        OptionValue::create(['option_id' => $color->id, 'name' => 'Blue', 'position' => 2]);
        OptionValue::create(['option_id' => $size->id, 'name' => 'Small', 'position' => 1]);
        OptionValue::create(['option_id' => $size->id, 'name' => 'Large', 'position' => 2]);

        $variants = app(GenerateVariants::class)->execute($product);

        expect($variants)->toHaveCount(4);
        $variants->each(fn ($v) => expect($v)->toBeInstanceOf(Variant::class));
    });

    it('dispatches VariantsGenerated event', function (): void {
        Event::fake([VariantsGenerated::class]);

        $product = Product::create([
            'name' => 'Variant Event',
            'price' => 5000,
            'type' => ProductType::Configurable,
            'supports_variants' => true,
        ]);

        $option = Option::create(['product_id' => $product->id, 'name' => 'Color', 'position' => 1]);
        OptionValue::create(['option_id' => $option->id, 'name' => 'Red', 'position' => 1]);
        OptionValue::create(['option_id' => $option->id, 'name' => 'Blue', 'position' => 2]);

        app(GenerateVariants::class)->execute($product);

        Event::assertDispatched(VariantsGenerated::class);
    });

    it('returns empty collection when product has no options', function (): void {
        $product = Product::create(['name' => 'No Options', 'price' => 1000]);

        $variants = app(GenerateVariants::class)->execute($product);

        expect($variants)->toHaveCount(0);
    });

    it('can be invoked', function (): void {
        $product = Product::create(['name' => 'Invokable Variant', 'price' => 1000, 'type' => ProductType::Configurable]);

        $option = Option::create(['product_id' => $product->id, 'name' => 'Size', 'position' => 1]);
        OptionValue::create(['option_id' => $option->id, 'name' => 'M', 'position' => 1]);

        $action = app(GenerateVariants::class);
        $variants = $action($product);

        expect($variants)->toHaveCount(1);
    });
});

describe('ApplyAttributeChanges Action', function (): void {
    it('applies attribute changes to a product', function (): void {
        $product = Product::create(['name' => 'Attribute Test', 'price' => 1000]);

        Attribute::create([
            'code' => 'test_color',
            'name' => 'Test Color',
            'type' => AttributeType::Text,
        ]);

        $product->setCustomAttribute('test_color', 'Red');

        $result = app(ApplyAttributeChanges::class)->execute($product, ['test_color' => 'Blue']);

        expect($result->getCustomAttribute('test_color'))->toBe('Blue');
    });

    it('can be invoked', function (): void {
        $product = Product::create(['name' => 'Invokable Attr', 'price' => 1000]);

        Attribute::create([
            'code' => 'test_invoke',
            'name' => 'Test Invoke',
            'type' => AttributeType::Text,
        ]);

        $product->setCustomAttribute('test_invoke', 'Old');

        $action = app(ApplyAttributeChanges::class);
        $result = $action($product, ['test_invoke' => 'New']);

        expect($result->getCustomAttribute('test_invoke'))->toBe('New');
    });
});
