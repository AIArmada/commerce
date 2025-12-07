<?php

declare(strict_types=1);

use AIArmada\Chip\Data\ProductData;
use Akaunting\Money\Money;

describe('Product data object', function (): void {
    it('calculates price helpers in currency', function (): void {
        $product = ProductData::fromArray([
            'name' => 'Premium Plan',
            'quantity' => 2,
            'price' => 19900,
            'discount' => 990,
            'tax_percent' => 6.0,
            'category' => 'subscription',
        ]);

        expect($product->getPriceInCurrency())->toBe(199.0);
        expect($product->getDiscountInCurrency())->toBe(9.90);
        expect($product->getTotalPrice()->getAmount())->toEqual((19900 - 990) * 2.0);
        expect($product->getTotalPriceInCurrency())->toBe(378.2);
    });

    it('exports to array for API payloads', function (): void {
        $product = ProductData::make('One-time Item', Money::MYR(5000));

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
