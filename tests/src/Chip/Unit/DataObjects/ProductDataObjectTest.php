<?php

declare(strict_types=1);

use AIArmada\Chip\DataObjects\Product;

describe('Product data object', function (): void {
    it('calculates price helpers in currency', function (): void {
        $product = Product::fromArray([
            'name' => 'Premium Plan',
            'quantity' => 2,
            'price' => 19900,
            'discount' => 990,
            'tax_percent' => 6.0,
            'category' => 'subscription',
        ]);

        expect($product->getPriceInCurrency())->toBe(199.0);
        expect($product->getDiscountInCurrency())->toBe(9.90);
        expect($product->getTotalPrice())->toEqual((19900 - 990) * 2);
        expect($product->getTotalPriceInCurrency())->toBe(378.2);
    });

    it('exports to array for API payloads', function (): void {
        $product = new Product('One-time Item', '1', 5000, 0, 0.0, null);

        expect($product->toArray())->toBe([
            'name' => 'One-time Item',
            'quantity' => '1',
            'price' => 5000,
            'discount' => 0,
            'tax_percent' => 0.0,
            'category' => null,
        ]);
    });
});
