<?php

declare(strict_types=1);

use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

describe('InteractsWithPaymentBehavior', function (): void {
    it('default incomplete', function (): void {
        $subscription = new Subscription;
        $subscription->defaultIncomplete();

        $this->assertEquals(Subscription::PAYMENT_BEHAVIOR_DEFAULT_INCOMPLETE, $subscription->paymentBehavior());
    });

    it('allow payment failures', function (): void {
        $subscription = new Subscription;
        $subscription->allowPaymentFailures();

        $this->assertEquals(Subscription::PAYMENT_BEHAVIOR_ALLOW_INCOMPLETE, $subscription->paymentBehavior());
    });

    it('pending if payment fails', function (): void {
        $subscription = new Subscription;
        $subscription->pendingIfPaymentFails();

        $this->assertEquals(Subscription::PAYMENT_BEHAVIOR_PENDING_IF_INCOMPLETE, $subscription->paymentBehavior());
    });

    it('error if payment fails', function (): void {
        $subscription = new Subscription;
        $subscription->errorIfPaymentFails();

        $this->assertEquals(Subscription::PAYMENT_BEHAVIOR_ERROR_IF_INCOMPLETE, $subscription->paymentBehavior());
    });

    it('set payment behavior', function (): void {
        $subscription = new Subscription;
        $subscription->setPaymentBehavior('custom_behavior');

        $this->assertEquals('custom_behavior', $subscription->paymentBehavior());
    });

    it('default payment behavior', function (): void {
        $subscription = new Subscription;

        $this->assertEquals('default_incomplete', $subscription->paymentBehavior());
    });
});
