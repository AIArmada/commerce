<?php

declare(strict_types=1);

use AIArmada\CashierChip\Invoice\Invoice;
use AIArmada\CashierChip\Invoice\InvoiceLineItem;
use AIArmada\Chip\Data\ProductData;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Akaunting\Money\Money;

uses(CashierChipTestCase::class);

describe('InvoiceLineItem', function (): void {
    it('it can be instantiated', function (): void {
        $invoice = Mockery::mock(Invoice::class);
        $invoice->shouldReceive('currency')->andReturn('MYR');

        $productData = ProductData::make(
            name: 'Test Product',
            price: Money::MYR(1000), // RM10.00
            quantity: 2
        );

        $item = new InvoiceLineItem($invoice, $productData, 1);

        $this->assertEquals('line_1', $item->id());
        $this->assertEquals('Test Product', $item->description());
        $this->assertEquals(2, $item->quantity());
        $this->assertEquals(1000, $item->unitPrice());
        $this->assertStringContainsString('10.00', $item->unitPriceFormatted());
        $this->assertEquals(2000, $item->total());
        $this->assertStringContainsString('20.00', $item->totalFormatted());
        $this->assertEquals('MYR', $item->currency());
    });

    it('serialization', function (): void {
        $invoice = Mockery::mock(Invoice::class);
        $invoice->shouldReceive('currency')->andReturn('MYR');

        $productData = ProductData::make(
            name: 'Test Product',
            price: Money::MYR(1000),
            quantity: 1
        );

        $item = new InvoiceLineItem($invoice, $productData);

        $array = $item->toArray();
        $this->assertEquals('line_0', $array['id']);
        $this->assertEquals('Test Product', $array['description']);

        $json = $item->toJson();
        $this->assertJson($json);
    });
});
