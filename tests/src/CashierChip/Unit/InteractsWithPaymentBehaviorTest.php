<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Subscription;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

class InteractsWithPaymentBehaviorTest extends CashierChipTestCase
{
    public function test_default_incomplete()
    {
        $subscription = new Subscription;
        $subscription->defaultIncomplete();

        $this->assertEquals(Subscription::PAYMENT_BEHAVIOR_DEFAULT_INCOMPLETE, $subscription->paymentBehavior());
    }

    public function test_allow_payment_failures()
    {
        $subscription = new Subscription;
        $subscription->allowPaymentFailures();

        $this->assertEquals(Subscription::PAYMENT_BEHAVIOR_ALLOW_INCOMPLETE, $subscription->paymentBehavior());
    }

    public function test_pending_if_payment_fails()
    {
        $subscription = new Subscription;
        $subscription->pendingIfPaymentFails();

        $this->assertEquals(Subscription::PAYMENT_BEHAVIOR_PENDING_IF_INCOMPLETE, $subscription->paymentBehavior());
    }

    public function test_error_if_payment_fails()
    {
        $subscription = new Subscription;
        $subscription->errorIfPaymentFails();

        $this->assertEquals(Subscription::PAYMENT_BEHAVIOR_ERROR_IF_INCOMPLETE, $subscription->paymentBehavior());
    }

    public function test_set_payment_behavior()
    {
        $subscription = new Subscription;
        $subscription->setPaymentBehavior('custom_behavior');

        $this->assertEquals('custom_behavior', $subscription->paymentBehavior());
    }

    public function test_default_payment_behavior()
    {
        $subscription = new Subscription;

        $this->assertEquals('default_incomplete', $subscription->paymentBehavior());
    }
}
