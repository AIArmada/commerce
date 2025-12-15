<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Invoice;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

class InvoiceTest extends CashierChipTestCase
{
    public function test_can_get_id(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals('pur_123', $invoice->id());
    }

    public function test_can_get_number(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid', 'reference' => 'INV-001']);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals('INV-001', $invoice->number());
    }

    public function test_number_falls_back_to_id(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals('pur_123', $invoice->number());
    }

    public function test_currency(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'paid',
            'payment' => ['currency' => 'MYR'],
        ]);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals('MYR', $invoice->currency());
    }

    public function test_raw_total(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'paid',
        ]);
        $invoice = new Invoice($user, $purchase);

        $this->assertIsInt($invoice->rawTotal());
    }

    public function test_total(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertIsString($invoice->total());
    }

    public function test_subtotal(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertIsString($invoice->subtotal());
    }

    public function test_has_tax(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertFalse($invoice->hasTax());
    }

    public function test_tax(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertIsString($invoice->tax());
    }

    public function test_has_discount(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertFalse($invoice->hasDiscount());
    }

    public function test_discount(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertIsString($invoice->discount());
    }

    public function test_invoice_items(): void
    {
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

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $items);
    }

    public function test_to_array(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertIsArray($invoice->toArray());
    }

    public function test_to_json(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertJson($invoice->toJson());
    }

    public function test_json_serialize(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertIsArray($invoice->jsonSerialize());
    }

    public function test_as_chip_purchase(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertSame($purchase, $invoice->asChipPurchase());
    }

    public function test_status(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals('paid', $invoice->status());
    }

    public function test_is_paid_when_paid(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertTrue($invoice->isPaid());
        $this->assertTrue($invoice->paid());
    }

    public function test_is_paid_when_not_paid(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'pending']);
        $invoice = new Invoice($user, $purchase);

        $this->assertFalse($invoice->isPaid());
    }

    public function test_is_open(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'pending']);
        $invoice = new Invoice($user, $purchase);

        $this->assertTrue($invoice->isOpen());
        $this->assertTrue($invoice->open());
    }

    public function test_is_not_open_when_paid(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertFalse($invoice->isOpen());
    }

    public function test_is_draft(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'created']);
        $invoice = new Invoice($user, $purchase);

        $this->assertTrue($invoice->isDraft());
    }

    public function test_is_voided(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'cancelled']);
        $invoice = new Invoice($user, $purchase);

        $this->assertTrue($invoice->voided());
        $this->assertTrue($invoice->isVoid());
    }

    public function test_is_uncollectible(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'failed']);
        $invoice = new Invoice($user, $purchase);

        $this->assertTrue($invoice->isUncollectible());
    }

    public function test_is_uncollectible_when_expired(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'expired']);
        $invoice = new Invoice($user, $purchase);

        $this->assertTrue($invoice->isUncollectible());
    }

    public function test_amount_due(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'pending']);
        $invoice = new Invoice($user, $purchase);

        $this->assertIsString($invoice->amountDue());
    }

    public function test_raw_amount_due_zero_when_paid(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals(0, $invoice->rawAmountDue());
    }

    public function test_amount_paid(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertIsString($invoice->amountPaid());
    }

    public function test_raw_amount_paid_zero_when_not_paid(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'pending']);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals(0, $invoice->rawAmountPaid());
    }

    public function test_checkout_url(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $purchase = PurchaseData::from([
            'id' => 'pur_123',
            'status' => 'pending',
            'checkout_url' => 'https://example.com/checkout',
        ]);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals('https://example.com/checkout', $invoice->checkoutUrl());
    }

    public function test_customer_name(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123', 'name' => 'John Doe']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals('John Doe', $invoice->customerName());
    }

    public function test_customer_email(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123', 'email' => 'john@example.com']);
        $purchase = PurchaseData::from(['id' => 'pur_123', 'status' => 'paid']);
        $invoice = new Invoice($user, $purchase);

        $this->assertEquals('john@example.com', $invoice->customerEmail());
    }
}
