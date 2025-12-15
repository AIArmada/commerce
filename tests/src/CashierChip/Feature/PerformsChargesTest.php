<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Feature;

use AIArmada\CashierChip\Payment;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

class PerformsChargesTest extends CashierChipTestCase
{
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
        $this->user->createAsChipCustomer();
    }

    public function test_it_can_perform_charges()
    {
        $payment = $this->user->charge(1000, null, ['product_name' => 'One Time Charge']);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertTrue($payment->rawAmount() >= 0);
        // In fake, it starts as 'created' unless charged immediately
        $this->assertContains($payment->status(), ['created', 'paid']);
    }

    public function test_it_can_create_payment()
    {
        $payment = $this->user->pay(2000);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertTrue($payment->rawAmount() >= 0);
    }

    public function test_it_can_refund_charge()
    {
        $payment = $this->user->charge(1000);
        $purchaseId = $payment->id();

        $refundData = $this->user->refund($purchaseId, 500);

        $this->assertInstanceOf(PurchaseData::class, $refundData);
    }

    public function test_find_payment()
    {
        $payment = $this->user->charge(1000);
        $found = $this->user->findPayment($payment->id());

        $this->assertInstanceOf(Payment::class, $found);
        $this->assertEquals($payment->id(), $found->id());
    }
}
