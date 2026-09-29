<?php

declare(strict_types=1);

use AIArmada\Chip\Data\ProductData;
use AIArmada\Chip\Exceptions\ChipValidationException;
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
        // Server formula (P25): (39800 - 990) x 1.06 = 41138.6, half-up.
        expect($product->getTotalPrice()->getAmount())->toEqual(41139);
        expect($product->getTotalPriceInCents())->toBe(41139);
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

    it('keeps a string tax percent as given without float precision loss', function (): void {
        $made = ProductData::make('String Tax', Money::MYR(5000), 1, null, '6');

        expect($made->toArray()['tax_percent'])->toBe('6')
            ->and($made->toRequestArray()['tax_percent'])->toBe('6');

        // from() used to cast to float: '0.4999…(22dp)' became 0.5, rounding
        // the line to 101 instead of 100.
        $parsed = ProductData::from(['name' => 'String Tax', 'price' => 100, 'tax_percent' => '0.4999999999999999999999']);

        expect($parsed->tax_percent)->toBe('0.4999999999999999999999')
            ->and($parsed->getTotalPriceInCents())->toBe(100);
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

    it('mirrors the server line-total formula on probe cases', function (): void {
        // (price x qty - discount) x (1 + tax/100), single half-up per line.
        $cases = [
            'P17' => [100, '1.5', 1, 0.0, null, 149],
            'P25a per-line discount' => [100, '3', 1, 0.0, null, 299],
            'P25c net-base tax' => [100, '1', 10, 10.0, null, 99],
            'P25d single round' => [100, '1.555', 0, 10.0, null, 171],
            'P25e TPO final with tax' => [1000, '3', 0, 10.0, 2500, 2500],
            'P25f discount above unit price' => [100, '3', 150, 0.0, null, 150],
            'disctax' => [100, '1.5', 1, 6.0, null, 158],
            'taxcombo' => [100, '3', 0, 6.0, null, 318],
            'half-cent exact (float64 misrounds)' => [100, '1.005', 0, 0.0, null, 101],
            'exponent quantity' => [100, '1e2', 0, 0.0, null, 10000],
            'zero quantity line' => [100, '0.0000', 0, 0.0, null, 0],
        ];

        foreach ($cases as $label => [$price, $qty, $discount, $tax, $tpo, $expected]) {
            expect(ProductData::lineTotalMinorUnits($price, $qty, $discount, $tax, $tpo))->toBe($expected, $label);
        }
    });

    it('rounds gross lines exactly at half-cent boundaries', function (): void {
        expect(ProductData::multiplyMinorUnits(100, '1.005'))->toBe(101)
            ->and(ProductData::multiplyMinorUnits(100, '1e2'))->toBe(10000);
    });

    it('rejects a discount above the line gross like the server', function (): void {
        expect(fn () => ProductData::lineTotalMinorUnits(100, '3', 500))
            ->toThrow(ChipValidationException::class, 'discount cannot be larger than price times quantity');
    });

    it('validates the discount bound even when a total price override is set', function (): void {
        // P25h: the server 400s product_subtotal_negative here (validation first).
        expect(fn () => ProductData::lineTotalMinorUnits(100, '3', 500, 0.0, 2500))
            ->toThrow(ChipValidationException::class, 'discount cannot be larger than price times quantity');
    });

    it('throws instead of saturating when a total exceeds the int range', function (): void {
        // 1025 x (2^53 - 1) is exact but unrepresentable as int64.
        expect(fn () => ProductData::lineTotalMinorUnits(1025, '9007199254740991'))
            ->toThrow(ChipValidationException::class, 'exceeds the supported range')
            ->and(fn () => ProductData::multiplyMinorUnits(1025, '9007199254740991'))
            ->toThrow(ChipValidationException::class, 'exceeds the supported range');
    });

    it('keeps both int64 edges representable', function (): void {
        expect(ProductData::multiplyMinorUnits(PHP_INT_MAX, '1'))->toBe(PHP_INT_MAX)
            ->and(ProductData::multiplyMinorUnits(PHP_INT_MIN, '1'))->toBe(PHP_INT_MIN);
    });

    it('rejects absurd precision instead of allocating unbounded strings', function (): void {
        expect(fn () => ProductData::lineTotalMinorUnits(100, '1', 0, '1e-2000'))
            ->toThrow(InvalidArgumentException::class, 'exceeds supported precision')
            ->and(fn () => ProductData::lineTotalMinorUnits(100, '0.' . str_repeat('1', 2000)))
            ->toThrow(InvalidArgumentException::class, 'exceeds supported precision');
    });

    it('rounds negative gross lines half-up away from zero', function (): void {
        expect(ProductData::multiplyMinorUnits(-1, '0.5'))->toBe(-1)
            ->and(ProductData::multiplyMinorUnits(-100, '1.005'))->toBe(-101);
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
