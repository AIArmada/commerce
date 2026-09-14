<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentPricing\Pages\PriceSimulator;
use AIArmada\Pricing\Contracts\PriceCalculatorInterface;
use AIArmada\Pricing\Data\PriceResultData;
use AIArmada\Products\Models\Product;

uses(TestCase::class);

it('leaves the result empty for invalid simulation input', function (array $data): void {
    $calculator = Mockery::mock(PriceCalculatorInterface::class);
    $calculator->shouldNotReceive('calculate');

    app()->instance(PriceCalculatorInterface::class, $calculator);

    $page = new PriceSimulator;
    $page->data = $data;
    $page->calculate();

    expect($page->result)->toBeNull();
})->with([
    'empty' => [[]],
    'unknown product type' => [['product_type' => 'coupon', 'product_id' => 'x', 'quantity' => 1]],
    'missing id' => [['product_type' => 'product', 'quantity' => 1]],
    'zero quantity' => [['product_type' => 'product', 'product_id' => 'x', 'quantity' => 0]],
    'negative quantity' => [['product_type' => 'product', 'product_id' => 'x', 'quantity' => -3]],
    'text quantity' => [['product_type' => 'product', 'product_id' => 'x', 'quantity' => 'many']],
    'absurd quantity' => [['product_type' => 'product', 'product_id' => 'x', 'quantity' => 2000000]],
]);

it('drops unparseable effective dates before calling the calculator', function (): void {
    $product = Product::factory()->create(['name' => 'Dated Simulation', 'price' => 2500]);

    $calculator = Mockery::mock(PriceCalculatorInterface::class);
    $calculator->shouldReceive('calculate')
        ->once()
        ->withArgs(fn ($item, $quantity, $context): bool => $item->is($product)
            && $quantity === 1
            && ! array_key_exists('effective_at', $context))
        ->andReturn(new PriceResultData(
            originalPrice: 2500,
            finalPrice: 2500,
            discountAmount: 0,
            discountSource: null,
            discountPercentage: null,
            priceListName: null,
            tierDescription: null,
            promotionName: null,
            breakdown: [],
        ));

    app()->instance(PriceCalculatorInterface::class, $calculator);

    $page = new PriceSimulator;
    $page->data = [
        'product_type' => 'product',
        'product_id' => (string) $product->getKey(),
        'quantity' => 1,
        'effective_date' => 'not-a-date-at-all',
    ];
    $page->calculate();

    expect($page->result)->not->toBeNull();
});
