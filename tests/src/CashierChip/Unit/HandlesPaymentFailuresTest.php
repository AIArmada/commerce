<?php

declare(strict_types=1);

use AIArmada\CashierChip\Exceptions\IncompletePayment;
use AIArmada\CashierChip\Payment\Payment;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;

uses(CashierChipTestCase::class);

describe('HandlesPaymentFailures', function (): void {
    it('it validates incomplete payments', function (): void {
        $user = new User;

        $payment = Mockery::mock(Payment::class);
        $payment->shouldReceive('validate')->once()->andThrow(new IncompletePayment($payment));

        $subscription = Mockery::mock(Subscription::class);
        $subscription->shouldReceive('hasIncompletePayment')->andReturn(true);
        $subscription->shouldReceive('latestPayment')->andReturn($payment);

        $user->handlePaymentFailure($subscription);
    })->throws(IncompletePayment::class);

    it('it can ignore incomplete payments', function (): void {
        $user = new User;

        $subscription = Mockery::mock(Subscription::class);
        // hasIncompletePayment shouldn't be called if validateIncompletePayment is false?
        // Code: if ($this->validateIncompletePayment && $subscription->hasIncompletePayment())
        // So short-circuit avoids the call.
        $subscription->shouldReceive('hasIncompletePayment')->never();

        $user->ignoreIncompletePayments()->handlePaymentFailure($subscription);

        $this->assertTrue(true);
    });
});
