<?php

declare(strict_types=1);

use AIArmada\Chip\DataObjects\Purchase;

describe('Purchase data object', function (): void {
    it('creates a purchase from array data', function (): void {
        $data = [
            'id' => 'purchase_123',
            'amount_in_cents' => 10000,
            'currency' => 'MYR',
            'reference' => 'ORDER_001',
            'checkout_url' => 'https://gate.chip-in.asia/checkout/purchase_123',
            'status' => 'created',
            'is_recurring' => false,
            'metadata' => ['order_id' => '123'],
            'created_at' => '2024-01-01T12:00:00Z',
            'updated_at' => '2024-01-01T12:00:00Z',
        ];

        $purchase = Purchase::fromArray($data);

        expect($purchase->id)->toBe('purchase_123');
        expect($purchase->amountInCents)->toBe(10000);
        expect($purchase->currency)->toBe('MYR');
        expect($purchase->reference)->toBe('ORDER_001');
        expect($purchase->checkoutUrl)->toBe('https://gate.chip-in.asia/checkout/purchase_123');
        expect($purchase->status)->toBe('created');
        expect($purchase->isRecurring)->toBeFalse();
        expect($purchase->metadata)->toBe(['order_id' => '123']);
    });

    it('handles nullable fields correctly', function (): void {
        $data = [
            'id' => 'purchase_123',
            'amount_in_cents' => 10000,
            'currency' => 'MYR',
            'status' => 'created',
        ];

        $purchase = Purchase::fromArray($data);

        expect($purchase->reference)->toBeNull();
        expect($purchase->checkoutUrl)->toBeNull();
        expect($purchase->metadata)->toBeNull();
        expect($purchase->clientId)->toBeNull();
    });

    it('calculates amount in major currency units', function (): void {
        $purchase = Purchase::fromArray([
            'id' => 'purchase_123',
            'amount_in_cents' => 12345,
            'currency' => 'MYR',
            'status' => 'created',
        ]);

        expect($purchase->getAmountInMajorUnits())->toBe(123.45);
    });

    it('exposes status helpers and array representation', function (): void {
        $data = [
            'id' => 'purchase_789',
            'status' => 'paid',
            'created_on' => strtotime('2024-02-01T12:00:00Z'),
            'updated_on' => strtotime('2024-02-02T12:00:00Z'),
            'due' => strtotime('2024-02-10T00:00:00Z'),
            'refundable_amount' => 2500,
            'refund_availability' => 'all',
            'client' => ['email' => 'buyer@example.com'],
            'purchase' => [
                'currency' => 'MYR',
                'total' => 2500,
                'products' => [['name' => 'Service', 'price' => 2500, 'quantity' => 1, 'discount' => 0, 'tax_percent' => 0.0]],
            ],
            'brand_id' => 'brand',
            'issuer_details' => [],
            'transaction_data' => [],
            'status_history' => [],
            'company_id' => 'company',
            'is_test' => false,
            'payment_method_whitelist' => ['fpx'],
        ];

        $purchase = Purchase::fromArray($data);

        expect($purchase->isPaid())->toBeTrue();
        expect($purchase->isRefunded())->toBeFalse();
        expect($purchase->canBeRefunded())->toBeTrue();
        expect($purchase->canBePartiallyRefunded())->toBeTrue();
        expect($purchase->getDueDate()?->toDateString())->toBe('2024-02-10');
        expect($purchase->getRefundableAmountInCurrency())->toBe(25.0);
        expect($purchase->toArray())->toMatchArray([
            'id' => 'purchase_789',
            'status' => 'paid',
            'refundable_amount' => 2500,
            'payment_method_whitelist' => ['fpx'],
        ]);
    });
});
