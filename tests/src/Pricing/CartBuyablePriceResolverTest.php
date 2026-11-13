<?php

declare(strict_types=1);

use AIArmada\Pricing\Contracts\PriceCalculatorInterface;
use AIArmada\Pricing\Data\PriceResultData;
use AIArmada\Pricing\Support\CartBuyablePriceResolver;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;

use function Pest\Laravel\mock;

it('quotes cart buyables through the pricing calculator with quantity and context', function (): void {
    $product = Product::create([
        'name' => 'Priced Product',
        'slug' => 'priced-product',
        'price' => 2000,
        'currency' => 'USD',
        'status' => ProductStatus::Active,
        'tracks_inventory' => false,
    ]);

    $calculator = mock(PriceCalculatorInterface::class);
    $calculator->shouldReceive('calculate')
        ->once()
        ->withArgs(function ($item, int $quantity, array $context) use ($product): bool {
            return $item->is($product)
                && $quantity === 3
                && ($context['customer_id'] ?? null) === 'customer-1'
                && ($context['currency'] ?? null) === 'USD';
        })
        ->andReturn(new PriceResultData(
            originalPrice: 6000,
            finalPrice: 5400,
            discountAmount: 600,
            discountSource: 'Price List',
            priceListName: 'Wholesale',
            currency: 'USD',
            breakdown: [['type' => 'price_list', 'price' => 5400, 'list' => 'Wholesale']],
        ));

    $resolver = new CartBuyablePriceResolver($calculator);
    $result = $resolver->resolve($product, 3, [
        'customer_id' => 'customer-1',
        'currency' => 'USD',
    ]);

    expect($result->unitPrice)->toBe(5400)
        ->and($result->originalUnitPrice)->toBe(6000)
        ->and($result->discountAmount)->toBe(600)
        ->and($result->priceListName)->toBe('Wholesale')
        ->and($result->currency)->toBe('USD');
});
