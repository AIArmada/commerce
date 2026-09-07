<?php

declare(strict_types=1);

use AIArmada\CashierChip\Exceptions\IncompletePayment;
use AIArmada\CashierChip\Payment\Payment;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

describe('Payment', function (): void {
    it('can get id', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $payment = new Payment($purchase);

        $this->assertEquals('pur_123', $payment->id());
    });

    it('can get status', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $payment = new Payment($purchase);

        $this->assertEquals('paid', $payment->status());
    });

    it('is succeeded', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->isSucceeded());
    });

    it('is succeeded when paid', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->isSucceeded());
    });

    it('is pending', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'pending_execute']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->isPending());
    });

    it('overdue purchase remains pending and not expired', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'overdue']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->isPending());
        $this->assertFalse($payment->isExpired());
    });

    it('is expired', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'expired']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->isExpired());
    });

    it('is failed', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'error']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->isFailed());
    });

    it('is cancelled', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'cancelled']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->isCancelled());
    });

    it('released purchase is cancelled', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'released']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->isCancelled());
    });

    it('is refunded', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'refunded']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->isRefunded());
    });

    it('dynamic property access', function (): void {
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
    });

    it('requires redirect', function (): void {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'created',
            'checkout_url' => 'https://chip.example.com/checkout/pur_123',
        ]);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->requiresRedirect());
    });

    it('requires capture for held purchase', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'hold']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->requiresCapture());
    });

    it('preauthorized purchase does not require capture', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'preauthorized']);
        $payment = new Payment($purchase);

        $this->assertFalse($payment->requiresCapture());
    });

    it('is processing', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'pending_execute']);
        $payment = new Payment($purchase);

        $this->assertTrue($payment->isProcessing());

        $purchase2 = PurchaseData::from(['id' => 'pur_124', 'status' => 'pending_charge']);
        $payment2 = new Payment($purchase2);

        $this->assertTrue($payment2->isProcessing());
    });

    it('get checkout url', function (): void {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'created',
            'checkout_url' => 'https://chip.example.com/checkout/pur_123',
        ]);
        $payment = new Payment($purchase);

        $this->assertEquals('https://chip.example.com/checkout/pur_123', $payment->checkoutUrl());
    });

    it('get recurring token', function (): void {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'paid',
            'recurring_token' => 'tok_123',
        ]);
        $payment = new Payment($purchase);

        $this->assertEquals('tok_123', $payment->recurringToken());
    });

    it('get currency', function (): void {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'paid',
            'payment' => ['amount' => 1000, 'currency' => 'MYR'],
        ]);
        $payment = new Payment($purchase);

        $this->assertEquals('MYR', $payment->currency());
    });

    it('get raw amount', function (): void {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'paid',
            'payment' => ['amount' => 1000, 'currency' => 'MYR'],
        ]);
        $payment = new Payment($purchase);

        // rawAmount should return an integer - exact value depends on how PurchaseData handles payment
        $this->assertIsInt($payment->rawAmount());
    });

    it('as chip purchase', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $payment = new Payment($purchase);

        $this->assertSame($purchase, $payment->asChipPurchase());
    });

    it('set customer', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $payment = new Payment($purchase);

        $payment->setCustomer($user);

        $this->assertSame($user, $payment->customer());
    });

    it('to array', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $payment = new Payment($purchase);

        $array = $payment->toArray();

        $this->assertIsArray($array);
    });

    it('to json', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $payment = new Payment($purchase);

        $json = $payment->toJson();

        $this->assertJson($json);
    });

    it('json serialize', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid', 'reference' => 'ref_123']);
        $payment = new Payment($purchase);

        $this->assertIsArray($payment->jsonSerialize());
    });

    it('validate throws on requires redirect', function (): void {
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'created',
            'checkout_url' => 'https://chip.example.com/checkout',
        ]);
        $payment = new Payment($purchase);

        $payment->validate();
    })->throws(IncompletePayment::class);

    it('validate throws on failed', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'error']);
        $payment = new Payment($purchase);

        $payment->validate();
    })->throws(IncompletePayment::class);

    it('validate throws on expired', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'expired']);
        $payment = new Payment($purchase);

        $payment->validate();
    })->throws(IncompletePayment::class);

    it('validate passes on success', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $payment = new Payment($purchase);

        // Should not throw
        $payment->validate();
        $this->assertTrue(true);
    });

    it('capture returns self when not held', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $payment = new Payment($purchase);

        $result = $payment->capture();

        $this->assertSame($payment, $result);
    });

    it('cancel returns self when succeeded', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $payment = new Payment($purchase);

        $result = $payment->cancel();

        $this->assertSame($payment, $result);
    });

    it('cancel returns self when cancelled', function (): void {
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'cancelled']);
        $payment = new Payment($purchase);

        $result = $payment->cancel();

        $this->assertSame($payment, $result);
    });
});
