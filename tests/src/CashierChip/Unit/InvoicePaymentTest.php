<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\InvoicePayment;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

class InvoicePaymentTest extends CashierChipTestCase
{
    public function test_can_get_id(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoicePayment = new InvoicePayment($purchase);

        $this->assertEquals('pur_123', $invoicePayment->id());
    }

    public function test_can_get_status(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoicePayment = new InvoicePayment($purchase);

        $this->assertEquals('paid', $invoicePayment->status());
    }

    public function test_is_completed(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoicePayment = new InvoicePayment($purchase);
        $this->assertTrue($invoicePayment->isCompleted());

        $purchaseSuccess = PurchaseData::from(['id' => 'pur_124', 'status' => 'success']);
        $invoicePaymentSuccess = new InvoicePayment($purchaseSuccess);
        $this->assertTrue($invoicePaymentSuccess->isCompleted());

        $purchaseFailed = PurchaseData::from(['id' => 'pur_125', 'status' => 'failed']);
        $invoicePaymentFailed = new InvoicePayment($purchaseFailed);
        $this->assertFalse($invoicePaymentFailed->isCompleted());
    }

    public function test_is_pending(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'pending']);
        $invoicePayment = new InvoicePayment($purchase);
        $this->assertTrue($invoicePayment->isPending());

        $purchaseCreated = PurchaseData::from(['id' => 'pur_124', 'status' => 'created']);
        $invoicePaymentCreated = new InvoicePayment($purchaseCreated);
        $this->assertTrue($invoicePaymentCreated->isPending());

        $purchasePaid = PurchaseData::from(['id' => 'pur_125', 'status' => 'paid']);
        $invoicePaymentPaid = new InvoicePayment($purchasePaid);
        $this->assertFalse($invoicePaymentPaid->isPending());
    }

    public function test_is_failed(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'failed']);
        $invoicePayment = new InvoicePayment($purchase);
        $this->assertTrue($invoicePayment->isFailed());

        $purchaseError = PurchaseData::from(['id' => 'pur_124', 'status' => 'error']);
        $invoicePaymentError = new InvoicePayment($purchaseError);
        $this->assertTrue($invoicePaymentError->isFailed());

        $purchasePaid = PurchaseData::from(['id' => 'pur_125', 'status' => 'paid']);
        $invoicePaymentPaid = new InvoicePayment($purchasePaid);
        $this->assertFalse($invoicePaymentPaid->isFailed());
    }

    public function test_can_get_raw_amount(): void
    {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'paid',
            'payment' => ['amount' => 1000, 'currency' => 'MYR'],
        ]);
        $invoicePayment = new InvoicePayment($purchase);

        $this->assertEquals(1000, $invoicePayment->rawAmount());
    }

    public function test_can_get_currency(): void
    {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'paid',
            'payment' => ['amount' => 1000, 'currency' => 'MYR'],
        ]);
        $invoicePayment = new InvoicePayment($purchase);

        $this->assertEquals('MYR', $invoicePayment->currency());
    }

    public function test_can_get_chip_purchase(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoicePayment = new InvoicePayment($purchase);

        $this->assertSame($purchase, $invoicePayment->asChipPurchase());
    }

    public function test_to_array(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoicePayment = new InvoicePayment($purchase);

        $array = $invoicePayment->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('is_completed', $array);
        $this->assertEquals('pur_123', $array['id']);
        $this->assertEquals('paid', $array['status']);
        $this->assertTrue($array['is_completed']);
    }

    public function test_to_json(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoicePayment = new InvoicePayment($purchase);

        $json = $invoicePayment->toJson();

        $this->assertJson($json);
        $this->assertStringContainsString('pur_123', $json);
    }

    public function test_json_serialize(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoicePayment = new InvoicePayment($purchase);

        $serialized = $invoicePayment->jsonSerialize();

        $this->assertIsArray($serialized);
        $this->assertEquals('pur_123', $serialized['id']);
    }

    public function test_dynamic_property_access(): void
    {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid', 'reference' => 'ref_123']);
        $invoicePayment = new InvoicePayment($purchase);

        // Dynamic __get should retrieve from purchase
        $this->assertEquals('pur_123', $invoicePayment->id);
        $this->assertEquals('paid', $invoicePayment->status);
    }
}
