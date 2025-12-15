<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Exceptions\IncompletePayment;
use AIArmada\CashierChip\Payment;
use AIArmada\CashierChip\Subscription;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use Mockery;

class HandlesPaymentFailuresTest extends CashierChipTestCase
{
    public function test_it_validates_incomplete_payments()
    {
        $user = new User;

        $payment = Mockery::mock(Payment::class);
        $payment->shouldReceive('validate')->once()->andThrow(new IncompletePayment($payment));

        $subscription = Mockery::mock(Subscription::class);
        $subscription->shouldReceive('hasIncompletePayment')->andReturn(true);
        $subscription->shouldReceive('latestPayment')->andReturn($payment);

        $this->expectException(IncompletePayment::class);

        $user->handlePaymentFailure($subscription);
    }

    public function test_it_can_ignore_incomplete_payments()
    {
        $user = new User;

        $subscription = Mockery::mock(Subscription::class);
        // hasIncompletePayment shouldn't be called if validateIncompletePayment is false?
        // Code: if ($this->validateIncompletePayment && $subscription->hasIncompletePayment())
        // So short-circuit avoids the call.
        $subscription->shouldReceive('hasIncompletePayment')->never();

        $user->ignoreIncompletePayments()->handlePaymentFailure($subscription);

        $this->assertTrue(true);
    }
}
