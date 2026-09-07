<?php

declare(strict_types=1);

use AIArmada\CashierChip\Payment\InvoicePayment;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

describe('InvoicePayment', function (): void {
    it('can get id', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoicePayment = new InvoicePayment($purchase);

        $this->assertEquals('pur_123', $invoicePayment->id());
    });

    it('can get status', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoicePayment = new InvoicePayment($purchase);

        $this->assertEquals('paid', $invoicePayment->status());
    });

    it('is completed', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoicePayment = new InvoicePayment($purchase);
        $this->assertTrue($invoicePayment->isCompleted());

        $purchaseSuccess = PurchaseData::from(['id' => 'pur_124', 'status' => 'cleared']);
        $invoicePaymentSuccess = new InvoicePayment($purchaseSuccess);
        $this->assertTrue($invoicePaymentSuccess->isCompleted());

        $purchaseFailed = PurchaseData::from(['id' => 'pur_125', 'status' => 'error']);
        $invoicePaymentFailed = new InvoicePayment($purchaseFailed);
        $this->assertFalse($invoicePaymentFailed->isCompleted());
    });

    it('is pending', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'pending_execute']);
        $invoicePayment = new InvoicePayment($purchase);
        $this->assertTrue($invoicePayment->isPending());

        $purchaseCreated = PurchaseData::from(['id' => 'pur_124', 'status' => 'created']);
        $invoicePaymentCreated = new InvoicePayment($purchaseCreated);
        $this->assertTrue($invoicePaymentCreated->isPending());

        $purchasePaid = PurchaseData::from(['id' => 'pur_125', 'status' => 'paid']);
        $invoicePaymentPaid = new InvoicePayment($purchasePaid);
        $this->assertFalse($invoicePaymentPaid->isPending());
    });

    it('overdue purchase is pending', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_126', 'status' => 'overdue']);
        $invoicePayment = new InvoicePayment($purchase);

        $this->assertTrue($invoicePayment->isPending());
    });

    it('is failed', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'error']);
        $invoicePayment = new InvoicePayment($purchase);
        $this->assertTrue($invoicePayment->isFailed());

        $purchaseError = PurchaseData::from(['id' => 'pur_124', 'status' => 'error']);
        $invoicePaymentError = new InvoicePayment($purchaseError);
        $this->assertTrue($invoicePaymentError->isFailed());

        $purchasePaid = PurchaseData::from(['id' => 'pur_125', 'status' => 'paid']);
        $invoicePaymentPaid = new InvoicePayment($purchasePaid);
        $this->assertFalse($invoicePaymentPaid->isFailed());
    });

    it('can get raw amount', function (): void {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'paid',
            'payment' => ['amount' => 1000, 'currency' => 'MYR'],
        ]);
        $invoicePayment = new InvoicePayment($purchase);

        $this->assertEquals(1000, $invoicePayment->rawAmount());
    });

    it('can get currency', function (): void {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'paid',
            'payment' => ['amount' => 1000, 'currency' => 'MYR'],
        ]);
        $invoicePayment = new InvoicePayment($purchase);

        $this->assertEquals('MYR', $invoicePayment->currency());
    });

    it('can get chip purchase', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoicePayment = new InvoicePayment($purchase);

        $this->assertSame($purchase, $invoicePayment->asChipPurchase());
    });

    it('to array', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoicePayment = new InvoicePayment($purchase);

        $array = $invoicePayment->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('is_completed', $array);
        $this->assertEquals('pur_123', $array['id']);
        $this->assertEquals('paid', $array['status']);
        $this->assertTrue($array['is_completed']);
    });

    it('to json', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoicePayment = new InvoicePayment($purchase);

        $json = $invoicePayment->toJson();

        $this->assertJson($json);
        $this->assertStringContainsString('pur_123', $json);
    });

    it('json serialize', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoicePayment = new InvoicePayment($purchase);

        $serialized = $invoicePayment->jsonSerialize();

        $this->assertIsArray($serialized);
        $this->assertEquals('pur_123', $serialized['id']);
    });

    it('dynamic property access', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid', 'reference' => 'ref_123']);
        $invoicePayment = new InvoicePayment($purchase);

        // Dynamic __get should retrieve from purchase
        $this->assertEquals('pur_123', $invoicePayment->id);
        $this->assertEquals('paid', $invoicePayment->status);
    });
});
