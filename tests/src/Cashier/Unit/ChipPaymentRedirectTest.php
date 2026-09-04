<?php

declare(strict_types=1);

use AIArmada\Cashier\Gateways\Chip\ChipPayment;
use AIArmada\CashierChip\Payment\Payment;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use Illuminate\Http\RedirectResponse;

uses(CashierTestCase::class);

describe('ChipPayment redirect handling', function (): void {
    it('throws a clear exception when redirect URL is unavailable', function (): void {
        $payment = Mockery::mock(Payment::class);
        $payment->shouldReceive('checkoutUrl')->andReturnNull();

        $chipPayment = new ChipPayment($payment);

        expect(fn () => $chipPayment->redirect())
            ->toThrow(InvalidArgumentException::class, 'CHIP payment requires action but no redirect URL is available.');
    });

    it('redirects when a valid checkout URL is present', function (): void {
        $payment = Mockery::mock(Payment::class);
        $payment->shouldReceive('checkoutUrl')->andReturn('https://chip.example.test/checkout');

        $chipPayment = new ChipPayment($payment);

        $response = $chipPayment->redirect();

        expect($response)->toBeInstanceOf(RedirectResponse::class)
            ->and($response->getTargetUrl())->toBe('https://chip.example.test/checkout');
    });

    it('represents a completed CHIP refund payment', function (): void {
        $refund = PaymentData::from([
            'id' => 'payment-refund-123',
            'type' => 'payment',
            'status' => 'refunded',
            'payment' => [
                'amount' => 500,
                'currency' => 'MYR',
                'net_amount' => 500,
                'fee_amount' => 0,
                'pending_amount' => 0,
                'payment_type' => 'refund',
                'is_outgoing' => true,
            ],
            'related_to' => [
                'type' => 'purchase',
                'id' => 'purchase-123',
            ],
        ]);

        $chipPayment = new ChipPayment($refund);

        expect($chipPayment->id())->toBe('payment-refund-123')
            ->and($chipPayment->status())->toBe('refunded')
            ->and($chipPayment->isRefunded())->toBeTrue()
            ->and($chipPayment->rawAmount())->toBe(500)
            ->and($chipPayment->currency())->toBe('MYR')
            ->and($chipPayment->asGatewayPayment())->toBe($refund);
    });
});
