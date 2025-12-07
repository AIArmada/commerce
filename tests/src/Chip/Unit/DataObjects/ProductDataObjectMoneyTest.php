<?php

declare(strict_types=1);

use AIArmada\Chip\Data\Product;
use Akaunting\Money\Money;

describe('Product data object with Money', function (): void {
    it('creates product from array with Money objects', function (): void {
        $product = Product::fromArray([
            'name' => 'Premium Plan',
            'quantity' => 2,
            'price' => 19900,
            'discount' => 990,
            'tax_percent' => 6.0,
            'category' => 'subscription',
        ]);

        expect($product->price)->toBeInstanceOf(Money::class)
            ->and($product->discount)->toBeInstanceOf(Money::class)
            ->and($product->getPriceInCents())->toBe(19900)
            ->and($product->getDiscountInCents())->toBe(990);
    });

    it('creates product using make() factory with Money', function (): void {
        $price = Money::MYR(5000);
        $discount = Money::MYR(500);

        $product = Product::make(
            name: 'Test Product',
            price: $price,
            quantity: 2,
            discount: $discount,
            taxPercent: 6.0,
            category: 'electronics',
        );

        expect($product->price)->toBe($price)
            ->and($product->discount)->toBe($discount)
            ->and($product->getCurrency())->toBe('MYR')
            ->and($product->getPriceInCents())->toBe(5000)
            ->and($product->getDiscountInCents())->toBe(500);
    });

    it('calculates total price as Money', function (): void {
        $product = Product::fromArray([
            'name' => 'Premium Plan',
            'quantity' => 2,
            'price' => 19900,
            'discount' => 990,
            'tax_percent' => 6.0,
            'category' => 'subscription',
        ]);

        $totalPrice = $product->getTotalPrice();

        expect($totalPrice)->toBeInstanceOf(Money::class)
            ->and($totalPrice->getAmount())->toBe(37820); // (19900 - 990) * 2
    });

    it('exports to array with prices in cents for API', function (): void {
        $product = Product::make(
            name: 'One-time Item',
            price: Money::MYR(5000),
        );

        expect($product->toArray())->toBe([
            'name' => 'One-time Item',
            'quantity' => '1',
            'price' => 5000,
            'discount' => 0,
            'tax_percent' => 0.0,
            'category' => null,
        ]);
    });

    it('handles different currencies', function (): void {
        $product = Product::fromArray([
            'name' => 'USD Product',
            'quantity' => 1,
            'price' => 1000,
        ], 'USD');

        expect($product->getCurrency())->toBe('USD')
            ->and($product->price->getCurrency()->getCurrency())->toBe('USD');
    });

    it('creates product with zero discount when not provided', function (): void {
        $product = Product::make(
            name: 'No Discount Product',
            price: Money::MYR(10000),
        );

        expect($product->discount)->toBeInstanceOf(Money::class)
            ->and($product->getDiscountInCents())->toBe(0)
            ->and($product->discount->getCurrency()->getCurrency())->toBe('MYR');
    });

    it('maintains backward compatibility with deprecated methods', function (): void {
        $product = Product::fromArray([
            'name' => 'Test',
            'quantity' => 1,
            'price' => 10000,
            'discount' => 1000,
        ]);

        // These methods are deprecated but should still work
        expect($product->getPriceInCurrency())->toBe(100.0)
            ->and($product->getDiscountInCurrency())->toBe(10.0)
            ->and($product->getTotalPriceInCurrency())->toBe(90.0);
    });
});
