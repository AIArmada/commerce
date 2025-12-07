<?php

declare(strict_types=1);

use AIArmada\Chip\Data\PaymentData;
use Akaunting\Money\Money;

describe('Payment data object with Money', function (): void {
    it('creates payment from array with Money objects', function (): void {
        $payment = PaymentData::fromArray([
            'is_outgoing' => false,
            'payment_type' => 'purchase',
            'amount' => 10000,
            'net_amount' => 9700,
            'fee_amount' => 300,
            'pending_amount' => 0,
            'currency' => 'MYR',
            'paid_on' => 1700000000,
        ]);

        expect($payment->amount)->toBeInstanceOf(Money::class)
            ->and($payment->net_amount)->toBeInstanceOf(Money::class)
            ->and($payment->fee_amount)->toBeInstanceOf(Money::class)
            ->and($payment->pending_amount)->toBeInstanceOf(Money::class);
    });

    it('returns amounts in cents for API communication', function (): void {
        $payment = PaymentData::fromArray([
            'amount' => 10000,
            'net_amount' => 9700,
            'fee_amount' => 300,
            'pending_amount' => 500,
            'currency' => 'MYR',
        ]);

        expect($payment->getAmountInCents())->toBe(10000)
            ->and($payment->getNetAmountInCents())->toBe(9700)
            ->and($payment->getFeeAmountInCents())->toBe(300)
            ->and($payment->getPendingAmountInCents())->toBe(500);
    });

    it('returns currency code from Money object', function (): void {
        $payment = PaymentData::fromArray([
            'amount' => 10000,
            'currency' => 'USD',
        ]);

        expect($payment->getCurrency())->toBe('USD')
            ->and($payment->amount->getCurrency()->getCurrency())->toBe('USD');
    });

    it('exports to array with amounts in cents', function (): void {
        $payment = PaymentData::fromArray([
            'is_outgoing' => true,
            'payment_type' => 'refund',
            'amount' => 5000,
            'net_amount' => 4850,
            'fee_amount' => 150,
            'pending_amount' => 0,
            'currency' => 'MYR',
            'description' => 'Test refund',
        ]);

        $array = $payment->toArray();

        expect($array['amount'])->toBe(5000)
            ->and($array['net_amount'])->toBe(4850)
            ->and($array['fee_amount'])->toBe(150)
            ->and($array['currency'])->toBe('MYR')
            ->and($array['is_outgoing'])->toBeTrue();
    });

    it('handles paid status with Money amounts', function (): void {
        $paidPayment = PaymentData::fromArray([
            'amount' => 10000,
            'currency' => 'MYR',
            'paid_on' => 1700000000,
        ]);

        $unpaidPayment = PaymentData::fromArray([
            'amount' => 10000,
            'currency' => 'MYR',
        ]);

        expect($paidPayment->isPaid())->toBeTrue()
            ->and($unpaidPayment->isPaid())->toBeFalse();
    });
});
