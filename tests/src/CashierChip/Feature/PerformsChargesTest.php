<?php

declare(strict_types=1);

use AIArmada\CashierChip\Payment\Payment;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

describe('PerformsCharges', function (): void {
    beforeEach(function (): void {
        $this->user = $this->createUser();
        $this->user->createAsChipCustomer();
    });

    it('it can perform charges', function (): void {
        $payment = $this->user->charge(1000, null, ['product_name' => 'One Time Charge']);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertTrue($payment->rawAmount() >= 0);
        // In fake, it starts as 'created' unless charged immediately
        $this->assertContains($payment->status(), ['created', 'paid']);
    });

    it('rejects charge amounts outside the configured bounds', function (): void {
        config()->set('cashier-chip.billing.max_amount_minor', 5000);

        expect(fn () => $this->user->charge(0))
            ->toThrow(InvalidArgumentException::class);
        expect(fn () => $this->user->charge(5001))
            ->toThrow(InvalidArgumentException::class);
    });

    it('it can create payment', function (): void {
        $payment = $this->user->pay(2000);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertTrue($payment->rawAmount() >= 0);
    });

    it('it can override the billable default currency', function (): void {
        $payment = $this->user->charge(1000, null, ['currency' => 'SGD']);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame('SGD', $payment->currency());
    });

    it('it can refund charge', function (): void {
        $payment = $this->user->charge(1000);
        $purchaseId = $payment->id();

        $refundData = $this->user->refund($purchaseId, 500);

        $this->assertInstanceOf(PaymentData::class, $refundData);
    });

    it('find payment', function (): void {
        $payment = $this->user->charge(1000);
        $found = $this->user->findPayment($payment->id());

        $this->assertInstanceOf(Payment::class, $found);
        $this->assertEquals($payment->id(), $found->id());
    });
});
