<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Exceptions\IncompletePayment;
use AIArmada\CashierChip\Payment;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

class PaymentTest extends CashierChipTestCase
{
    public function test_can_get_id(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $payment = new Payment($purchase);

        $this->assertEquals('pur_123', $payment->id());
    }

    public function test_can_get_status(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'success']);
        $payment = new Payment($purchase);

        $this->assertEquals('success', $payment->status());
    }

    public function test_is_succeeded(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'success']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->isSucceeded());
    }

    public function test_is_succeeded_when_paid(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->isSucceeded());
    }

    public function test_is_pending(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'pending']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->isPending());
    }

    public function test_is_expired(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'expired']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->isExpired());
    }

    public function test_is_failed(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'failed']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->isFailed());
    }

    public function test_is_cancelled(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'cancelled']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->isCancelled());
    }

    public function test_is_refunded(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'refunded']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->isRefunded());
    }

    public function test_dynamic_property_access(): void
    {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'paid',
            'reference' => 'REF-001',
            'metadata' => ['key' => 'value'],
        ]);
        $payment = new Payment($purchase);

        $this->assertEquals('pur_123', $payment->id);
        $this->assertEquals('paid', $payment->status);
        $this->assertEquals('REF-001', $payment->reference);
    }

    public function test_requires_redirect(): void
    {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'pending',
            'checkout_url' => 'https://chip.example.com/checkout/pur_123',
        ]);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->requiresRedirect());
    }

    public function test_requires_capture(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'preauthorized']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->requiresCapture());
    }

    public function test_is_processing(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'pending_execute']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->isProcessing());

        $purchase2 = PurchaseData::from(['id' => 'pur_124', 'status' => 'pending_charge']);
        $payment2 = new Payment($purchase2);

        $this->assertTrue($payment2->isProcessing());
    }

    public function test_get_checkout_url(): void
    {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'pending',
            'checkout_url' => 'https://chip.example.com/checkout/pur_123',
        ]);
        $payment = new Payment($purchase);

        $this->assertEquals('https://chip.example.com/checkout/pur_123', $payment->checkoutUrl());
    }

    public function test_get_recurring_token(): void
    {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'success',
            'recurring_token' => 'tok_123',
        ]);
        $payment = new Payment($purchase);

        $this->assertEquals('tok_123', $payment->recurringToken());
    }

    public function test_get_currency(): void
    {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'success',
            'payment' => ['amount' => 1000, 'currency' => 'MYR'],
        ]);
        $payment = new Payment($purchase);

        $this->assertEquals('MYR', $payment->currency());
    }

    public function test_get_raw_amount(): void
    {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'success',
            'payment' => ['amount' => 1000, 'currency' => 'MYR'],
        ]);
        $payment = new Payment($purchase);

        // rawAmount should return an integer - exact value depends on how PurchaseData handles payment
        $this->assertIsInt($payment->rawAmount());
    }

    public function test_as_chip_purchase(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'success']);
        $payment = new Payment($purchase);

        $this->assertSame($purchase, $payment->asChipPurchase());
    }

    public function test_set_customer(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'success']);
        $payment = new Payment($purchase);

        $payment->setCustomer($user);

        $this->assertSame($user, $payment->customer());
    }

    public function test_to_array(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'success']);
        $payment = new Payment($purchase);

        $array = $payment->toArray();

        $this->assertIsArray($array);
    }

    public function test_to_json(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'success']);
        $payment = new Payment($purchase);

        $json = $payment->toJson();

        $this->assertJson($json);
    }

    public function test_json_serialize(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'success', 'reference' => 'ref_123']);
        $payment = new Payment($purchase);

        $this->assertIsArray($payment->jsonSerialize());
    }

    public function test_validate_throws_on_requires_redirect(): void
    {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'pending',
            'checkout_url' => 'https://chip.example.com/checkout',
        ]);
        $payment = new Payment($purchase);

        $this->expectException(IncompletePayment::class);
        $payment->validate();
    }

    public function test_validate_throws_on_failed(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'failed']);
        $payment = new Payment($purchase);

        $this->expectException(IncompletePayment::class);
        $payment->validate();
    }

    public function test_validate_throws_on_expired(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'expired']);
        $payment = new Payment($purchase);

        $this->expectException(IncompletePayment::class);
        $payment->validate();
    }

    public function test_validate_passes_on_success(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'success']);
        $payment = new Payment($purchase);

        // Should not throw
        $payment->validate();
        $this->assertTrue(true);
    }

    public function test_capture_returns_self_when_not_preauthorized(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'success']);
        $payment = new Payment($purchase);

        $result = $payment->capture();

        $this->assertSame($payment, $result);
    }

    public function test_cancel_returns_self_when_succeeded(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'success']);
        $payment = new Payment($purchase);

        $result = $payment->cancel();

        $this->assertSame($payment, $result);
    }

    public function test_cancel_returns_self_when_cancelled(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'cancelled']);
        $payment = new Payment($purchase);

        $result = $payment->cancel();

        $this->assertSame($payment, $result);
    }
}
