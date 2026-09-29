<?php

declare(strict_types=1);

use AIArmada\Chip\Data\ProductData;
use Akaunting\Money\Money;

describe('Product data object', function (): void {
    it('calculates price helpers in cents', function (): void {
        $product = ProductData::from([
            'name' => 'Premium Plan',
            'quantity' => 2,
            'price' => 19900,
            'discount' => 990,
            'tax_percent' => 6.0,
            'category' => 'subscription',
        ]);

        expect($product->getPriceInCents())->toBe(19900);
        expect($product->getDiscountInCents())->toBe(990);
        expect($product->getTotalPrice()->getAmount())->toEqual((19900 - 990) * 2.0);
        expect($product->getTotalPriceInCents())->toBe(37820);
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
            'total_price_override' => null,
        ]);
    });

    it('round-trips a total price override including zero', function (): void {
        $product = ProductData::make('Override Item', Money::MYR(5000), 1, null, 0.0, null, 0);

        expect($product->toArray()['total_price_override'])->toBe(0)
            ->and($product->toRequestArray()['total_price_override'])->toBe(0);

        $restored = ProductData::from($product->toRequestArray());

        expect($restored->total_price_override)->toBe(0);
    });

    it('emits a string tax percent as given but parses responses to float', function (): void {
        $made = ProductData::make('String Tax', Money::MYR(5000), 1, null, '6');

        expect($made->toArray()['tax_percent'])->toBe('6')
            ->and($made->toRequestArray()['tax_percent'])->toBe('6');

        $parsed = ProductData::from(['name' => 'String Tax', 'price' => 5000, 'tax_percent' => '0.00']);

        expect($parsed->tax_percent)->toBe(0.0);
    });

    it('exports a request array without nulls for the CHIP wire', function (): void {
        $product = ProductData::make('One-time Item', Money::MYR(5000));

        expect($product->toRequestArray())->toBe([
            'name' => 'One-time Item',
            'quantity' => '1',
            'price' => 5000,
            'discount' => 0,
            'tax_percent' => 0.0,
        ]);
    });

    it('round-trips through the request array', function (): void {
        $product = ProductData::make('One-time Item', Money::MYR(5000));

        $request = $product->toRequestArray();
        $restored = ProductData::from($request);

        expect($request)->not->toHaveKey('category')
            ->and($restored->toArray())->toBe($product->toArray());
    });

    it('keeps an empty-string category in the request array', function (): void {
        $product = ProductData::from(['name' => 'X', 'price' => 1, 'category' => '']);

        expect($product->toRequestArray()['category'])->toBe('');
    });

    it('keeps set values in the request array', function (): void {
        $product = ProductData::from([
            'name' => 'Taxed Item',
            'quantity' => 2,
            'price' => 19900,
            'discount' => 990,
            'tax_percent' => 6.0,
            'category' => 'subscription',
        ]);

        expect($product->toRequestArray())->toBe([
            'name' => 'Taxed Item',
            'quantity' => '2',
            'price' => 19900,
            'discount' => 990,
            'tax_percent' => 6.0,
            'category' => 'subscription',
        ]);
    });
});
