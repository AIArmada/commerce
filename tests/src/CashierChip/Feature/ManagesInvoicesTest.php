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
});
