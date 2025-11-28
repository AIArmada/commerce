<?php

declare(strict_types=1);

use AIArmada\Chip\DataObjects\Product;
use AIArmada\Chip\DataObjects\PurchaseDetails;
use Akaunting\Money\Money;

describe('PurchaseDetails data object with Money', function (): void {
    it('creates purchase details from array with Money objects', function (): void {
        $data = [
            'currency' => 'MYR',
            'products' => [
                ['name' => 'Product A', 'quantity' => 1, 'price' => 10000],
                ['name' => 'Product B', 'quantity' => 2, 'price' => 5000],
            ],
            'total' => 20000,
            'language' => 'en',
            'debt' => 500,
        ];

        $details = PurchaseDetails::fromArray($data);

        expect($details->total)->toBeInstanceOf(Money::class)
            ->and($details->debt)->toBeInstanceOf(Money::class)
            ->and($details->getTotalInCents())->toBe(20000)
            ->and($details->products[0])->toBeInstanceOf(Product::class)
            ->and($details->products[0]->price)->toBeInstanceOf(Money::class);
    });

    it('calculates subtotal as Money from products', function (): void {
        $data = [
            'currency' => 'MYR',
            'products' => [
                ['name' => 'Product A', 'quantity' => 1, 'price' => 10000],
                ['name' => 'Product B', 'quantity' => 2, 'price' => 5000],
            ],
            'total' => 20000,
        ];

        $details = PurchaseDetails::fromArray($data);
        $subtotal = $details->getSubtotal();

        expect($subtotal)->toBeInstanceOf(Money::class)
            ->and($subtotal->getAmount())->toBe(20000) // 10000 + (5000 * 2)
            ->and($details->getSubtotalInCents())->toBe(20000);
    });

    it('handles override amounts as Money', function (): void {
        $data = [
            'currency' => 'MYR',
            'products' => [],
            'total' => 10000,
            'subtotal_override' => 8000,
            'total_tax_override' => 600,
            'total_discount_override' => 500,
            'total_override' => 9100,
        ];

        $details = PurchaseDetails::fromArray($data);

        expect($details->subtotal_override)->toBeInstanceOf(Money::class)
            ->and($details->total_tax_override)->toBeInstanceOf(Money::class)
            ->and($details->total_discount_override)->toBeInstanceOf(Money::class)
            ->and($details->total_override)->toBeInstanceOf(Money::class)
            ->and($details->subtotal_override->getAmount())->toBe(8000)
            ->and($details->total_tax_override->getAmount())->toBe(600);
    });

    it('exports to array with amounts in cents for API', function (): void {
        $details = new PurchaseDetails(
            currency: 'MYR',
            products: [Product::make('Test', Money::MYR(1000))],
            total: Money::MYR(1000),
            language: 'en',
            notes: null,
            debt: Money::MYR(0),
            subtotal_override: Money::MYR(900),
            total_tax_override: null,
            total_discount_override: Money::MYR(100),
            total_override: null,
            request_client_details: ['email' => true],
            timezone: 'Asia/Kuala_Lumpur',
            due_strict: false,
            email_message: null,
            metadata: null,
        );

        $array = $details->toArray();

        expect($array['total'])->toBe(1000)
            ->and($array['debt'])->toBe(0)
            ->and($array['subtotal_override'])->toBe(900)
            ->and($array['total_discount_override'])->toBe(100)
            ->and($array['total_tax_override'])->toBeNull()
            ->and($array['products'][0]['price'])->toBe(1000);
    });

    it('maintains backward compatibility with deprecated methods', function (): void {
        $data = [
            'currency' => 'MYR',
            'products' => [
                ['name' => 'Test', 'quantity' => 1, 'price' => 10000],
            ],
            'total' => 10000,
        ];

        $details = PurchaseDetails::fromArray($data);

        expect($details->getTotalInCurrency())->toBe(100.0)
            ->and($details->getSubtotalInCurrency())->toBe(100.0);
    });

    it('handles null override values', function (): void {
        $data = [
            'currency' => 'MYR',
            'products' => [],
            'total' => 5000,
        ];

        $details = PurchaseDetails::fromArray($data);

        expect($details->subtotal_override)->toBeNull()
            ->and($details->total_tax_override)->toBeNull()
            ->and($details->total_discount_override)->toBeNull()
            ->and($details->total_override)->toBeNull();
    });

    it('passes currency to products when creating from array', function (): void {
        $data = [
            'currency' => 'USD',
            'products' => [
                ['name' => 'USD Product', 'quantity' => 1, 'price' => 5000],
            ],
            'total' => 5000,
        ];

        $details = PurchaseDetails::fromArray($data);

        expect($details->products[0]->getCurrency())->toBe('USD')
            ->and($details->products[0]->price->getCurrency()->getCurrency())->toBe('USD');
    });
});
