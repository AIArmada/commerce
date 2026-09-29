<?php

declare(strict_types=1);

use AIArmada\CashierChip\Invoice\Invoice;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Illuminate\Support\Collection;

uses(CashierChipTestCase::class);

describe('ManagesInvoices', function (): void {
    beforeEach(function (): void {
        $this->user = $this->createUser();
        $this->user->createAsChipCustomer();
    });

    it('find invoice', function (): void {
        $payment = $this->user->charge(1000);
        $invoice = $this->user->findInvoice($payment->id());

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals($payment->id(), $invoice->id());

        // Debug assertions
        $items = $invoice->invoiceItems();
        $this->assertCount(1, $items);
        $this->assertEquals(1000, $items->first()->total());

        $this->assertEquals(1000, $invoice->rawTotal());
    });

    it('excludes one-off charges from subscription invoices', function (): void {
        $this->user->charge(2000);

        // invoices() is subscription-derived, so a one-off charge yields none.
        $invoices = $this->user->invoices();

        $this->assertInstanceOf(Collection::class, $invoices);
        $this->assertCount(0, $invoices);
    });

    it('dedupes invoicing on the idempotency key', function (): void {
        $this->user->tab('Item', 1000);
        $first = $this->user->invoice(['idempotency_key' => 'invoice-key-1']);

        $this->user->tab('Item', 1000);
        $second = $this->user->invoice(['idempotency_key' => 'invoice-key-1']);

        $this->assertEquals($first->id(), $second->id());
    });

    it('creates distinct invoices for distinct keys', function (): void {
        $this->user->tab('Item', 1000);
        $first = $this->user->invoice(['idempotency_key' => 'invoice-key-a']);

        $this->user->tab('Item', 1000);
        $second = $this->user->invoice(['idempotency_key' => 'invoice-key-b']);

        $this->assertNotEquals($first->id(), $second->id());
    });
});
