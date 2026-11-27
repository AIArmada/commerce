<?php

declare(strict_types=1);

use AIArmada\CashierChip\Exceptions\IncompletePayment;
use AIArmada\CashierChip\Payment\Payment;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;

uses(CashierChipTestCase::class);

describe('HandlesPaymentFailures', function (): void {
    it('it validates incomplete payments', function (): void {
        $user = new User;

        $payment = Mockery::mock(Payment::class);
        $payment->shouldReceive('validate')->once()->andThrow(new IncompletePayment($payment));

        $user->handlePaymentFailure(fn (): Payment => $payment);
    })->throws(IncompletePayment::class);

    it('it can ignore incomplete payments', function (): void {
        $user = new User;

        $user->ignoreIncompletePayments()->handlePaymentFailure(function (): ?Payment {
            throw new RuntimeException('Ignored payment should not be retrieved.');
        });

        $this->assertTrue(true);
    });
});
