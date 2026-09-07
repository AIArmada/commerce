<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use Exception;

uses(CashierChipTestCase::class);

describe('ManagesInvoices', function (): void {
    it('invoices returns empty without chip id', function (): void {
        $user = new User(['email' => 'test@example.com']);

        $invoices = $user->invoices();

        $this->assertCount(0, $invoices);
    });

    it('invoices including pending', function (): void {
        $user = new User(['email' => 'test@example.com']);

        $invoices = $user->invoicesIncludingPending();

        $this->assertCount(0, $invoices);
    });

    it('find invoice returns null on error', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $invoice = $user->findInvoice('non_existent_invoice');

        $this->assertNull($invoice);
    });

    it('upcoming invoice returns null without subscription', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $invoice = $user->upcomingInvoice();

        $this->assertNull($invoice);
    });

    it('tab stores items', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $result = $user->tab('Test Item', 1000);

        $this->assertSame($user, $result);
        $this->assertIsArray($user->tabs);
        $this->assertCount(1, $user->tabs);
        $this->assertEquals('Test Item', $user->tabs[0]['name']);
        $this->assertEquals(1000, $user->tabs[0]['price']);
    });

    it('tab supports quantity', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $user->tab('Test Item', 1000, ['quantity' => 5]);

        $this->assertEquals(5, $user->tabs[0]['quantity']);
    });

    it('multiple tabs', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $user->tab('Item 1', 1000);
        $user->tab('Item 2', 2000);

        $this->assertCount(2, $user->tabs);
    });

    it('invoice throws without tabs', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $user->invoice();
    })->throws(Exception::class, 'No items to invoice');
});
