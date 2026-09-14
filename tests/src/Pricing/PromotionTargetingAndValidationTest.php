<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Targeting\TargetingEngine;
use AIArmada\Pricing\Actions\ApplyPromotionalAdjustment;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\PriceTier;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use AIArmada\Promotions\Contracts\PromotionServiceInterface;
use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Promotions\Services\PromotionService;
use Illuminate\Support\Str;

beforeEach(function (): void {
    app()->instance(PromotionServiceInterface::class, new PromotionService(new TargetingEngine));
});

it('matches category rules against the real priceable model', function (): void {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['price' => 10000]);
    $product->categories()->attach($category->getKey());

    Promotion::create([
        'name' => 'Category Deal',
        'type' => PromotionType::Percentage,
        'discount_value' => 10,
        'is_active' => true,
        'conditions' => [
            'mode' => 'all',
            'rules' => [
                ['type' => 'category_in_cart', 'operator' => 'in', 'values' => [$category->slug]],
            ],
        ],
    ]);

    $result = app(ApplyPromotionalAdjustment::class)->apply(
        $product->getMorphClass(),
        (string) $product->getKey(),
        10000,
        1,
        now()->toImmutable(),
    );

    expect($result)->not->toBeNull()
        ->and($result['name'])->toBe('Category Deal')
        ->and($result['price'])->toBe(9000);
});

it('keeps id-only matching when the priceable cannot be resolved', function (): void {
    $itemId = 'unresolvable-' . uniqid();

    Promotion::create([
        'name' => 'Id Deal',
        'type' => PromotionType::Percentage,
        'discount_value' => 10,
        'is_active' => true,
        'conditions' => [
            'mode' => 'all',
            'rules' => [
                ['type' => 'product_in_cart', 'operator' => 'in', 'values' => [$itemId]],
            ],
        ],
    ]);

    $result = app(ApplyPromotionalAdjustment::class)->apply(
        'App\\Models\\Missing',
        $itemId,
        10000,
        1,
        now()->toImmutable(),
    );

    expect($result)->not->toBeNull()
        ->and($result['name'])->toBe('Id Deal');
});

it('rejects negative price amounts', function (): void {
    $list = PriceList::create([
        'name' => 'Validation List',
        'slug' => 'validation-list-' . uniqid(),
        'currency' => 'MYR',
    ]);

    expect(fn () => Price::create([
        'price_list_id' => $list->id,
        'priceable_type' => Product::class,
        'priceable_id' => (string) Str::uuid(),
        'amount' => -100,
        'currency' => 'MYR',
    ]))->toThrow(InvalidArgumentException::class, 'zero or greater');
});

it('rejects zero minimum quantities', function (): void {
    $list = PriceList::create([
        'name' => 'Validation List',
        'slug' => 'validation-list-' . uniqid(),
        'currency' => 'MYR',
    ]);

    expect(fn () => Price::create([
        'price_list_id' => $list->id,
        'priceable_type' => Product::class,
        'priceable_id' => (string) Str::uuid(),
        'amount' => 100,
        'min_quantity' => 0,
        'currency' => 'MYR',
    ]))->toThrow(InvalidArgumentException::class, 'at least 1');
});

it('rejects inverted schedules', function (): void {
    $list = PriceList::create([
        'name' => 'Validation List',
        'slug' => 'validation-list-' . uniqid(),
        'currency' => 'MYR',
    ]);

    expect(fn () => Price::create([
        'price_list_id' => $list->id,
        'priceable_type' => Product::class,
        'priceable_id' => (string) Str::uuid(),
        'amount' => 100,
        'currency' => 'MYR',
        'starts_at' => now()->addDay(),
        'ends_at' => now(),
    ]))->toThrow(InvalidArgumentException::class, 'starts_at');
});

it('rejects tier ranges below their minimum', function (): void {
    expect(fn () => PriceTier::create([
        'tierable_type' => Product::class,
        'tierable_id' => (string) Str::uuid(),
        'min_quantity' => 10,
        'max_quantity' => 5,
        'amount' => 100,
        'currency' => 'MYR',
    ]))->toThrow(InvalidArgumentException::class, 'max quantity');
});

it('keeps activation flags in sync through the transition helpers', function (): void {
    $list = PriceList::create([
        'name' => 'Flag List',
        'slug' => 'flag-list-' . uniqid(),
        'currency' => 'MYR',
    ]);

    $list->deactivate();

    expect($list->is_active)->toBeFalse()
        ->and($list->deactivated_at)->not->toBeNull()
        ->and($list->isActive())->toBeFalse();

    $list->activate();

    expect($list->is_active)->toBeTrue()
        ->and($list->deactivated_at)->toBeNull()
        ->and($list->isActive())->toBeTrue();
});
