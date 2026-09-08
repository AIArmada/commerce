<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

/**
 * ADVERSARY PROOF — Attack 3: `findInvoice()` returns any gateway purchase
 * without checking it belongs to the billable.
 *
 * `ManagesInvoices::findInvoice()` fetches an arbitrary purchase id from the
 * gateway and wraps it in an `Invoice` for `$this` — it never compares the
 * purchase's `client_id` with the billable's CHIP customer id. The reachable
 * entrypoint is the customer-portal `downloadInvoice($invoiceId)` Livewire
 * action (`filament-cashier-chip/.../CustomerPortal/Pages/Invoices.php:59`),
 * where `$invoiceId` is caller-supplied: any authenticated billable can
 * download any other tenant's invoice (amounts, customer email, line items).
 * The gateway-level twin `AbstractGateway::findInvoice()` ignores its
 * `$billable` argument the same way.
 *
 * Mock seam: `ChipCollectService` (the gateway-client boundary for the
 * cashier-chip package). The `findInvoice` logic under test is unmocked.
 */
it('refuses to resolve a foreign purchase as the billable invoice', function (): void {
    Cashier::unfake();

    $foreignPurchase = PurchaseData::from([
        'id' => 'purchase-foreign-adv',
        'created_on' => 1704067200,
        'updated_on' => 1704070800,
        'client' => ['email' => 'other-tenant@example.com'],
        'client_id' => 'chip_cus_foreign_tenant',
        'purchase' => [
            'currency' => 'MYR',
            'total' => 5000,
            'products' => [[
                'name' => 'Other tenant item',
                'price' => 5000,
                'quantity' => 1,
                'discount' => 0,
                'tax_percent' => 0.0,
            ]],
        ],
        'brand_id' => 'test_brand_id',
        'status' => 'paid',
    ]);

    $service = Mockery::mock(ChipCollectService::class);
    $service->shouldReceive('getPurchase')
        ->once()
        ->with('purchase-foreign-adv')
        ->andReturn($foreignPurchase);
    app()->instance(ChipCollectService::class, $service);

    $billable = $this->createUser([
        'name' => 'Invoice Adversary',
        'email' => 'invoice-adv@example.com',
        'chip_id' => 'chip_cus_adv_owner',
    ]);

    // The purchase belongs to chip_cus_foreign_tenant, not to this billable.
    $invoice = $billable->findInvoice('purchase-foreign-adv');

    expect($invoice)->toBeNull('findInvoice() returned another tenant purchase as our invoice.');
});
