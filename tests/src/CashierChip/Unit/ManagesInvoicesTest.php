<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use Exception;

class ManagesInvoicesTest extends CashierChipTestCase
{
    public function test_invoices_returns_empty_without_chip_id(): void
    {
        $user = new User(['email' => 'test@example.com']);

        $invoices = $user->invoices();

        $this->assertCount(0, $invoices);
    }

    public function test_invoices_including_pending(): void
    {
        $user = new User(['email' => 'test@example.com']);

        $invoices = $user->invoicesIncludingPending();

        $this->assertCount(0, $invoices);
    }

    public function test_find_invoice_returns_null_on_error(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $invoice = $user->findInvoice('non_existent_invoice');

        $this->assertNull($invoice);
    }

    public function test_upcoming_invoice_returns_null_without_subscription(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $invoice = $user->upcomingInvoice();

        $this->assertNull($invoice);
    }

    public function test_tab_stores_items(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $result = $user->tab('Test Item', 1000);

        $this->assertSame($user, $result);
        $this->assertIsArray($user->tabs);
        $this->assertCount(1, $user->tabs);
        $this->assertEquals('Test Item', $user->tabs[0]['name']);
        $this->assertEquals(1000, $user->tabs[0]['price']);
    }

    public function test_tab_supports_quantity(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $user->tab('Test Item', 1000, ['quantity' => 5]);

        $this->assertEquals(5, $user->tabs[0]['quantity']);
    }

    public function test_multiple_tabs(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $user->tab('Item 1', 1000);
        $user->tab('Item 2', 2000);

        $this->assertCount(2, $user->tabs);
    }

    public function test_invoice_throws_without_tabs(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No items to invoice');

        $user->invoice();
    }
}
