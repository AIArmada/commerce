<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentProducts\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Products\Actions\DuplicateProduct;
use AIArmada\Products\Actions\UpdateCatalogProducts;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(TestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('products.features.owner.enabled', true);
    config()->set('products.features.owner.include_global', false);
    config()->set('products.features.owner.auto_assign_on_create', true);

    $owner = TestOwner::query()->create(['name' => 'Owner']);

    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?RecordReference
        {
            return EloquentRecordReference::fromModel($this->owner);
        }
    });

    $this->owner = $owner;
});

function duplicateTestProduct(Product $record): Product
{
    return app(DuplicateProduct::class)->execute($record);
}

it('duplicates a product with its relations and unique identity', function (): void {
    $owner = $this->owner;

    $product = OwnerContext::withOwner($owner, static function () use ($owner): Product {
        $product = Product::query()->create([
            'name' => 'Original',
            'slug' => 'original',
            'sku' => 'ORIG-1',
            'price' => 1000,
            'status' => ProductStatus::Active,
        ]);

        $category = Category::query()->create(['name' => 'Cat', 'slug' => 'dup-cat']);
        $product->categories()->sync([$category->id]);

        $option = Option::query()->create([
            'product_id' => $product->id,
            'name' => 'Size',
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);
        $value = OptionValue::query()->create([
            'option_id' => $option->id,
            'name' => 'Large',
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);

        $variant = Variant::query()->create([
            'product_id' => $product->id,
            'sku' => 'ORIG-1-L',
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);
        $variant->optionValues()->sync([$value->id]);

        $priceList = PriceList::query()->create([
            'name' => 'Retail',
            'slug' => 'retail',
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);
        Price::query()->create([
            'price_list_id' => $priceList->id,
            'priceable_type' => $product->getMorphClass(),
            'priceable_id' => $product->id,
            'amount' => 1000,
            'currency' => 'MYR',
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);

        return $product;
    });

    $copy = OwnerContext::withOwner($owner, static fn (): Product => duplicateTestProduct($product));

    expect($copy->getKey())->not->toBe($product->getKey())
        ->and($copy->slug)->toBe('original-copy')
        ->and($copy->sku)->toBe('ORIG-1-COPY')
        ->and($copy->status)->toBe(ProductStatus::Draft)
        ->and($copy->categories->pluck('id')->all())->toBe($product->categories->pluck('id')->all())
        ->and($copy->options)->toHaveCount(1)
        ->and($copy->options->first()->values)->toHaveCount(1)
        ->and($copy->variants)->toHaveCount(1)
        ->and($copy->variants->first()->optionValues)->toHaveCount(1)
        ->and($copy->variants->first()->sku)->toBe('ORIG-1-L-COPY')
        ->and($copy->prices()->count())->toBe(1);

    $secondCopy = OwnerContext::withOwner($owner, static fn (): Product => duplicateTestProduct($product));

    expect($secondCopy->slug)->toBe('original-copy-2')
        ->and($secondCopy->sku)->toBe('ORIG-1-COPY-2');
});

it('applies validated bulk price adjustments in minor units', function (): void {
    OwnerContext::withOwner($this->owner, function (): void {
        $product = Product::query()->create([
            'name' => 'Adjustable product',
            'slug' => 'adjustable-product',
            'sku' => 'ADJUSTABLE',
            'price' => 1000,
            'status' => ProductStatus::Active,
        ]);
        $action = app(UpdateCatalogProducts::class);

        $action->adjustPrices([$product], 'set', '12.34');
        expect($product->fresh()->price)->toBe(1234);

        $action->adjustPrices([$product], 'increase_amount', 2.5);
        expect($product->fresh()->price)->toBe(1484);

        $action->adjustPrices([$product], 'decrease_amount', 2.5);
        expect($product->fresh()->price)->toBe(1234);

        $action->adjustPrices([$product], 'increase_percent', 10);
        expect($product->fresh()->price)->toBe(1357);

        $action->adjustPrices([$product], 'decrease_percent', 25);
        expect($product->fresh()->price)->toBe(1018);

        expect(fn () => $action->adjustPrices([$product], 'increase_percent', 500))->toThrow(ValidationException::class)
            ->and(fn () => $action->adjustPrices([$product], 'bogus', 10))->toThrow(ValidationException::class)
            ->and($product->fresh()->price)->toBe(1018);
    });
});
