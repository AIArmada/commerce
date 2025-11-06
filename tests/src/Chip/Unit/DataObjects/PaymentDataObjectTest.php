<?php

declare(strict_types=1);

use AIArmada\Chip\DataObjects\Payment;

describe('Payment data object', function (): void {
    it('creates a payment from array data', function (): void {
        $data = [
            'is_outgoing' => false,
            'payment_type' => 'purchase',
            'amount' => 10000,
            'currency' => 'MYR',
            'net_amount' => 9950,
            'fee_amount' => 50,
            'pending_amount' => 0,
            'pending_unfreeze_on' => null,
            'description' => 'Test payment',
            'paid_on' => 1640995800,
            'remote_paid_on' => 1640995800,
        ];

        $payment = Payment::fromArray($data);

        expect($payment->payment_type)->toBe('purchase');
        expect($payment->amount)->toBe(10000);
        expect($payment->net_amount)->toBe(9950);
        expect($payment->fee_amount)->toBe(50);
        expect($payment->currency)->toBe('MYR');
        expect($payment->description)->toBe('Test payment');
        expect($payment->paid_on)->toBe(1640995800);
    });

    it('handles null payment method', function (): void {
        $payment = Payment::fromArray([
            'is_outgoing' => false,
            'payment_type' => 'purchase',
            'amount' => 10000,
            'currency' => 'MYR',
            'net_amount' => 10000,
            'fee_amount' => 0,
            'pending_amount' => 0,
            'pending_unfreeze_on' => null,
            'description' => null,
            'paid_on' => null,
            'remote_paid_on' => null,
        ]);

        expect($payment->description)->toBeNull();
        expect($payment->paid_on)->toBeNull();
    });

    it('calculates net amount after fees', function (): void {
        $payment = Payment::fromArray([
            'is_outgoing' => false,
            'payment_type' => 'purchase',
            'amount' => 10000,
            'currency' => 'MYR',
            'net_amount' => 9950,
            'fee_amount' => 50,
            'pending_amount' => 0,
            'pending_unfreeze_on' => null,
            'description' => 'Test payment',
            'paid_on' => 1640995800,
            'remote_paid_on' => 1640995800,
        ]);

        expect($payment->getNetAmountInMajorUnits())->toBe(99.50);
        expect($payment->getFeeAmountInMajorUnits())->toBe(0.50);
    });

    it('exposes paid timestamps and array output', function (): void {
        $payment = Payment::fromArray([
            'is_outgoing' => true,
            'payment_type' => 'refund',
            'amount' => 5000,
            'currency' => 'MYR',
            'net_amount' => 4800,
            'fee_amount' => 200,
            'pending_amount' => 100,
            'pending_unfreeze_on' => 1641082200,
            'description' => 'Partial refund',
            'paid_on' => 1641080400,
            'remote_paid_on' => 1641080400,
        ]);

        expect($payment->isPaid())->toBeTrue();
        expect($payment->getPaidAt()?->timestamp)->toBe(1641080400);
        expect($payment->getPendingUnfreezeAt()?->timestamp)->toBe(1641082200);
        expect($payment->toArray())->toMatchArray([
            'payment_type' => 'refund',
            'pending_unfreeze_on' => 1641082200,
            'is_outgoing' => true,
        ]);
    });
});
