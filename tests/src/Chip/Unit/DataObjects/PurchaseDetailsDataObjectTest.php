<?php

declare(strict_types=1);

use AIArmada\Chip\DataObjects\Product;
use AIArmada\Chip\DataObjects\PurchaseDetails;

describe('PurchaseDetails data object', function (): void {
    it('calculates total and subtotal amounts', function (): void {
        $data = [
            'currency' => 'MYR',
            'products' => [
                ['name' => 'Product A', 'quantity' => 1, 'price' => 10000, 'discount' => 0, 'tax_percent' => 0.0],
                ['name' => 'Product B', 'quantity' => 2, 'price' => 5000, 'discount' => 500, 'tax_percent' => 0.0],
            ],
            'total' => 19500,
            'language' => 'en',
            'notes' => 'Test purchase',
            'debt' => 0,
            'subtotal_override' => null,
            'total_tax_override' => null,
            'total_discount_override' => null,
            'total_override' => null,
            'request_client_details' => [],
            'timezone' => 'Asia/Kuala_Lumpur',
            'due_strict' => false,
            'email_message' => null,
            'metadata' => ['order_id' => 'ORD-123'],
        ];

        $details = PurchaseDetails::fromArray($data);

        expect($details->getTotalInCurrency())->toBe(195.0);
        expect($details->getSubtotalInCurrency())->toEqual((10000 + (5000 * 2)) / 100);
        expect($details->products[0])->toBeInstanceOf(Product::class);
    });

    it('exports to array with nested products', function (): void {
        $details = new PurchaseDetails(
            currency: 'MYR',
            products: [new Product('Custom', '1', 1000, 0, 0.0, null)],
            total: 1000,
            language: 'en',
            notes: null,
            debt: 0,
            subtotal_override: null,
            total_tax_override: null,
            total_discount_override: null,
            total_override: null,
            request_client_details: ['email' => true],
            timezone: 'Asia/Kuala_Lumpur',
            due_strict: true,
            email_message: 'Thanks',
            metadata: null,
        );

        expect($details->toArray())->toMatchArray([
            'currency' => 'MYR',
            'products' => [[
                'name' => 'Custom',
                'quantity' => '1',
                'price' => 1000,
                'discount' => 0,
                'tax_percent' => 0.0,
                'category' => null,
            ]],
            'total' => 1000,
            'language' => 'en',
            'notes' => null,
            'debt' => 0,
            'subtotal_override' => null,
            'total_tax_override' => null,
            'total_discount_override' => null,
            'total_override' => null,
            'request_client_details' => ['email' => true],
            'timezone' => 'Asia/Kuala_Lumpur',
            'due_strict' => true,
            'email_message' => 'Thanks',
        ]);
    });
});
