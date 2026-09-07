<?php

declare(strict_types=1);

use AIArmada\CashierChip\Invoice\Invoice;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Illuminate\Support\Collection;

uses(CashierChipTestCase::class);

describe('Invoice', function (): void {
    it('can get id', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals('pur_123', $invoice->id());
    });

    it('can get number', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid', 'reference' => 'INV-001']);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals('INV-001', $invoice->number());
    });

    it('number falls back to id', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals('pur_123', $invoice->number());
    });

    it('currency', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'paid',
            'payment' => ['currency' => 'MYR'],
        ]);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals('MYR', $invoice->currency());
    });

    it('raw total', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'paid',
        ]);
        $invoice = new Invoice($user, $purchase);

        $this->assertIsInt($invoice->rawTotal());
    });

    it('total', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertIsString($invoice->total());
    });

    it('subtotal', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertIsString($invoice->subtotal());
    });

    it('has tax', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertFalse($invoice->hasTax());
    });

    it('tax', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertIsString($invoice->tax());
    });

    it('has discount', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertFalse($invoice->hasDiscount());
    });

    it('discount', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertIsString($invoice->discount());
    });

    it('invoice items', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'paid',
            'purchase' => [
                'products' => [
                    ['name' => 'Product 1', 'price' => 10.00, 'quantity' => 1],
                ],
            ],
        ]);
        $invoice = new Invoice($user, $purchase);

        $items = $invoice->invoiceItems();

        $this->assertInstanceOf(Collection::class, $items);
    });

    it('to array', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertIsArray($invoice->toArray());
    });

    it('to json', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertJson($invoice->toJson());
    });

    it('json serialize', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertIsArray($invoice->jsonSerialize());
    });

    it('as chip purchase', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertSame($purchase, $invoice->asChipPurchase());
    });

    it('status', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals('paid', $invoice->status());
    });

    it('is paid when paid', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertTrue($invoice->isPaid());
        $this->assertTrue($invoice->paid());
    });

    it('is paid when not paid', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'pending_execute']);
        $invoice = new Invoice($user, $purchase);

        $this->assertFalse($invoice->isPaid());
    });

    it('is open', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'pending_execute']);
        $invoice = new Invoice($user, $purchase);

        $this->assertTrue($invoice->isOpen());
        $this->assertTrue($invoice->open());
    });

    it('is not open when paid', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertFalse($invoice->isOpen());
    });

    it('is draft', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'created']);
        $invoice = new Invoice($user, $purchase);

        $this->assertTrue($invoice->isDraft());
    });

    it('is voided', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'cancelled']);
        $invoice = new Invoice($user, $purchase);

        $this->assertTrue($invoice->voided());
        $this->assertTrue($invoice->isVoid());
    });

    it('is uncollectible', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'error']);
        $invoice = new Invoice($user, $purchase);

        $this->assertTrue($invoice->isUncollectible());
    });

    it('is uncollectible when expired', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'expired']);
        $invoice = new Invoice($user, $purchase);

        $this->assertTrue($invoice->isUncollectible());
    });

    it('amount due', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'pending_execute']);
        $invoice = new Invoice($user, $purchase);

        $this->assertIsString($invoice->amountDue());
    });

    it('raw amount due zero when paid', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals(0, $invoice->rawAmountDue());
    });

    it('amount paid', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertIsString($invoice->amountPaid());
    });

    it('raw amount paid zero when not paid', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'pending_execute']);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals(0, $invoice->rawAmountPaid());
    });

    it('checkout url', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'pending_execute',
            'checkout_url' => 'https://example.com/checkout',
        ]);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals('https://example.com/checkout', $invoice->checkoutUrl());
    });

    it('customer name', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123', 'name' => 'John Doe']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals('John Doe', $invoice->customerName());
    });

    it('customer email', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123', 'email' => 'john@example.com']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals('john@example.com', $invoice->customerEmail());
    });
});
