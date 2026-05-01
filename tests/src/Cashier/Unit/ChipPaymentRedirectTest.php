<?php

declare(strict_types=1);

use AIArmada\Cashier\Gateways\Chip\ChipPayment;
use AIArmada\CashierChip\Payment;
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
});
