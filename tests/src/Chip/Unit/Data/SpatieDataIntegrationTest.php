<?php

declare(strict_types=1);

use AIArmada\Chip\Data\Casts\MoneyCast;
use AIArmada\Chip\Data\Collections\ProductCollection;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\ProductData;
use AIArmada\Chip\Data\Transformers\MoneyTransformer;
use Akaunting\Money\Money;

describe('MoneyCast', function (): void {
    it('can be instantiated with default currency', function (): void {
        $cast = new MoneyCast(currency: 'MYR');
        expect($cast)->toBeInstanceOf(MoneyCast::class);
    });

    it('can be instantiated with currency property reference', function (): void {
        $cast = new MoneyCast(currencyProperty: 'currency');
        expect($cast)->toBeInstanceOf(MoneyCast::class);
    });

    it('can be instantiated without arguments', function (): void {
        $cast = new MoneyCast();
        expect($cast)->toBeInstanceOf(MoneyCast::class);
    });
});

describe('MoneyTransformer', function (): void {
    it('can be instantiated', function (): void {
        $transformer = new MoneyTransformer();
        expect($transformer)->toBeInstanceOf(MoneyTransformer::class);
    });
});

describe('ProductCollection', function (): void {
    it('creates a typed collection of ProductData', function (): void {
        $products = [
            ProductData::make('Product A', Money::MYR(1000)),
            ProductData::make('Product B', Money::MYR(2000)),
        ];

        $collection = new ProductCollection($products);

        expect($collection)->toBeInstanceOf(ProductCollection::class);
        expect($collection->count())->toBe(2);
    });

    it('calculates total price in cents', function (): void {
        $products = [
            ProductData::make('Product A', Money::MYR(1000), quantity: 2),
            ProductData::make('Product B', Money::MYR(500), quantity: 3),
        ];

        $collection = new ProductCollection($products);

        // Product A: 1000 * 2 = 2000 cents
        // Product B: 500 * 3 = 1500 cents
        // Total: 3500 cents
        expect($collection->getTotalPriceInCents())->toBe(3500);
    });

    it('calculates subtotal in cents', function (): void {
        $products = [
            ProductData::make('Product A', Money::MYR(1000), quantity: 2),
        ];

        $collection = new ProductCollection($products);

        // Subtotal: price × quantity = 1000 * 2 = 2000 cents
        expect($collection->getSubtotalInCents())->toBe(2000);
    });

    it('calculates total discount in cents', function (): void {
        $products = [
            ProductData::make('Product A', Money::MYR(1000), quantity: 2, discount: Money::MYR(100)),
            ProductData::make('Product B', Money::MYR(500), quantity: 1, discount: Money::MYR(50)),
        ];

        $collection = new ProductCollection($products);

        // Product A: 100 * 2 = 200 cents discount
        // Product B: 50 * 1 = 50 cents discount
        // Total: 250 cents
        expect($collection->getTotalDiscountInCents())->toBe(250);
    });

    it('returns Money object for total price', function (): void {
        $products = [
            ProductData::make('Product A', Money::MYR(1000)),
        ];

        $collection = new ProductCollection($products);
        $total = $collection->getTotalPrice('MYR');

        expect($total)->toBeInstanceOf(Money::class);
        expect($total->getCurrency()->getCurrency())->toBe('MYR');
    });

    it('handles empty collection', function (): void {
        $collection = new ProductCollection([]);

        expect($collection->count())->toBe(0);
        expect($collection->getTotalPriceInCents())->toBe(0);
        expect($collection->getSubtotalInCents())->toBe(0);
        expect($collection->getTotalDiscountInCents())->toBe(0);
    });
});

describe('DTOs with Spatie attributes', function (): void {
    it('ProductData can be created via from() with cents', function (): void {
        $product = ProductData::from([
            'name' => 'Test Product',
            'price' => 1500,
            'quantity' => '2',
            'currency' => 'MYR',
        ]);

        expect($product->name)->toBe('Test Product');
        expect($product->price)->toBeInstanceOf(Money::class);
        expect($product->getPriceInCents())->toBe(1500);
        expect($product->quantity)->toBe('2');
    });

    it('ProductData serializes Money back to cents', function (): void {
        $product = ProductData::make('Test', Money::MYR(2500), quantity: 1);
        $array = $product->toArray();

        expect($array['price'])->toBe(2500);
        expect($array['discount'])->toBe(0);
    });

    it('PaymentData can be created with Money amounts', function (): void {
        $payment = PaymentData::from([
            'amount' => 5000,
            'net_amount' => 4800,
            'fee_amount' => 200,
            'pending_amount' => 0,
            'currency' => 'MYR',
        ]);

        expect($payment->amount)->toBeInstanceOf(Money::class);
        expect($payment->getAmountInCents())->toBe(5000);
        expect($payment->net_amount)->toBeInstanceOf(Money::class);
        expect($payment->getNetAmountInCents())->toBe(4800);
    });
});
